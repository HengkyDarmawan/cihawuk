<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * RBAC berbasis permission + batas akses per objek tiket (deny-by-default).
 * Semua pemeriksaan berjalan di server; menu yang disembunyikan bukan kontrol akses.
 */
class AuthorizationService {

	/** @var CI_Controller */
	protected $CI;

	/** @var array<int, string[]> */
	protected $perm_cache = array();

	/** @var array<int, array> */
	protected $scope_cache = array();

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('User_model', 'user_model');
	}

	public function flush($user_id = NULL)
	{
		if ($user_id === NULL)
		{
			$this->perm_cache = array();
			$this->scope_cache = array();
		}
		else
		{
			unset($this->perm_cache[(int) $user_id], $this->scope_cache[(int) $user_id]);
		}
	}

	public function permissions($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id <= 0)
		{
			return array();
		}
		if ( ! isset($this->perm_cache[$user_id]))
		{
			// Super Admin memegang seluruh permission, termasuk permission yang ditambahkan kemudian.
			$this->perm_cache[$user_id] = in_array('super_admin', $this->CI->user_model->role_codes($user_id), TRUE)
				? $this->all_permission_codes()
				: $this->CI->user_model->permission_codes($user_id);
		}
		return $this->perm_cache[$user_id];
	}

	/** @return string[] */
	protected function all_permission_codes()
	{
		$codes = array();
		foreach ($this->CI->db->select('code')->get('permissions')->result() as $row)
		{
			$codes[] = $row->code;
		}
		return $codes;
	}

	public function user_can($user_id, $permission)
	{
		return in_array($permission, $this->permissions($user_id), TRUE);
	}

	public function user_can_any($user_id, array $permissions)
	{
		return count(array_intersect($permissions, $this->permissions($user_id))) > 0;
	}

	/** Permission untuk pengguna yang sedang login. */
	public function can($permission)
	{
		$uid = $this->CI->auth->user_id();
		return $uid !== NULL && $this->user_can($uid, $permission);
	}

	public function can_any(array $permissions)
	{
		$uid = $this->CI->auth->user_id();
		return $uid !== NULL && $this->user_can_any($uid, $permissions);
	}

	public function require_permission($permission)
	{
		if ( ! $this->can($permission))
		{
			throw new AccessDeniedException('Missing permission '.$permission);
		}
	}

	/** Unit yang dapat dipantau: ['all' => bool, 'units' => int[]] */
	public function monitor_scope($user_id)
	{
		$user_id = (int) $user_id;
		if ( ! isset($this->scope_cache[$user_id]))
		{
			$all = FALSE;
			$units = array();
			foreach ($this->CI->user_model->unit_scopes($user_id) as $scope)
			{
				if ($scope->scope_type === 'all')
				{
					$all = TRUE;
				}
				if (in_array($scope->scope_type, array('monitor', 'member', 'all'), TRUE))
				{
					$units[] = (int) $scope->unit_id;
				}
			}
			$this->scope_cache[$user_id] = array('all' => $all, 'units' => array_values(array_unique($units)));
		}
		return $this->scope_cache[$user_id];
	}

	public function has_conflict($user_id, $ticket_id)
	{
		return $this->CI->db->where(array('ticket_id' => (int) $ticket_id, 'user_id' => (int) $user_id))
			->count_all_results('ticket_conflicts') > 0;
	}

	/**
	 * Kemampuan petugas terhadap satu tiket.
	 * @return array<string,bool>
	 */
	public function ticket_abilities($user_id, $ticket)
	{
		$none = array(
			'view' => FALSE, 'verify' => FALSE, 'assign' => FALSE, 'work' => FALSE, 'close' => FALSE,
			'reopen' => FALSE, 'view_identity' => FALSE, 'internal_notes' => FALSE, 'monitor' => FALSE,
		);
		$user_id = (int) $user_id;
		if ($user_id <= 0 OR ! $ticket)
		{
			return $none;
		}
		$p = $this->permissions($user_id);
		$has = function ($code) use ($p) { return in_array($code, $p, TRUE); };

		// Petugas terlapor / konflik kepentingan tidak mendapat akses apa pun.
		if ($this->has_conflict($user_id, $ticket->id))
		{
			return $none;
		}
		// Pelapor sendiri tidak menangani laporannya di area pengelola.
		if ($ticket->reporter_user_id !== NULL && (int) $ticket->reporter_user_id === $user_id)
		{
			return $none;
		}

		$confidential_ok = ($ticket->confidentiality !== 'restricted') || $has('tickets.handle_confidential');
		if ( ! $confidential_ok)
		{
			return $none;
		}

		$is_assignee = $ticket->assigned_user_id !== NULL && (int) $ticket->assigned_user_id === $user_id;
		$scope = $this->monitor_scope($user_id);
		$in_scope = $scope['all'] || ($ticket->assigned_unit_id !== NULL && in_array((int) $ticket->assigned_unit_id, $scope['units'], TRUE));

		$a = $none;
		$a['verify'] = $has('tickets.verify');
		$a['monitor'] = $has('tickets.monitor_scope') && $in_scope;
		$a['work'] = $has('tickets.work_assigned') && $is_assignee;
		$a['assign'] = $has('tickets.assign') && ($a['verify'] OR $a['monitor']);
		$a['view'] = $a['verify'] || $a['monitor'] || $a['work'];
		$a['close'] = $has('tickets.close') && $a['view'];
		$a['reopen'] = $has('tickets.reopen') && $a['view'];
		$a['view_identity'] = $has('tickets.view_identity') && $a['view'];
		$a['internal_notes'] = $a['view'];
		return $a;
	}

	/**
	 * Tambahkan kondisi WHERE lingkup tiket untuk daftar/ekspor/badge (alias tabel `t`).
	 * Mengembalikan FALSE bila pengguna tidak memiliki akses daftar sama sekali.
	 */
	public function apply_ticket_scope($db, $user_id)
	{
		$user_id = (int) $user_id;
		$p = $this->permissions($user_id);
		$verify = in_array('tickets.verify', $p, TRUE);
		$monitor = in_array('tickets.monitor_scope', $p, TRUE);
		$work = in_array('tickets.work_assigned', $p, TRUE);
		$confidential = in_array('tickets.handle_confidential', $p, TRUE);

		if ( ! $verify && ! $monitor && ! $work)
		{
			return FALSE;
		}

		$clauses = array();
		if ($verify)
		{
			$clauses[] = '1 = 1';
		}
		else
		{
			if ($work)
			{
				$clauses[] = 't.assigned_user_id = '.$user_id;
			}
			if ($monitor)
			{
				$scope = $this->monitor_scope($user_id);
				if ($scope['all'])
				{
					$clauses[] = '1 = 1';
				}
				elseif ( ! empty($scope['units']))
				{
					$clauses[] = 't.assigned_unit_id IN ('.implode(',', array_map('intval', $scope['units'])).')';
				}
			}
		}
		if (empty($clauses))
		{
			return FALSE;
		}
		$db->where('('.implode(' OR ', $clauses).')', NULL, FALSE);
		if ( ! $confidential)
		{
			$db->where('t.confidentiality', 'private');
		}
		$db->where('NOT EXISTS (SELECT 1 FROM ticket_conflicts tc WHERE tc.ticket_id = t.id AND tc.user_id = '.$user_id.')', NULL, FALSE);
		$db->where('(t.reporter_user_id IS NULL OR t.reporter_user_id <> '.$user_id.')', NULL, FALSE);
		return TRUE;
	}

	/** Apakah pengguna dapat menjadi penanggung jawab tiket ini. */
	public function can_be_assignee($user_id, $ticket)
	{
		$user = $this->CI->user_model->find($user_id);
		if ( ! $user OR $user->account_status !== 'active')
		{
			return FALSE;
		}
		if ( ! $this->user_can($user_id, 'tickets.work_assigned'))
		{
			return FALSE;
		}
		if ($ticket->confidentiality === 'restricted' && ! $this->user_can($user_id, 'tickets.handle_confidential'))
		{
			return FALSE;
		}
		if ($ticket->reporter_user_id !== NULL && (int) $ticket->reporter_user_id === (int) $user_id)
		{
			return FALSE;
		}
		return ! $this->has_conflict($user_id, $ticket->id);
	}
}
