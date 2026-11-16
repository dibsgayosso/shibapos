<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Shiba_kennel extends CI_Model
{
	public function dashboard($location)
	{
		$parents = $this->db->where('location_id', $location)->order_by('name')->get('shiba_parents')->result_array();
		$litters = $this->db->select('l.*, sire.name AS sire, dam.name AS dam')
			->from('shiba_litters l')->join('shiba_parents sire', 'sire.id = l.sire_id')
			->join('shiba_parents dam', 'dam.id = l.dam_id')->where('l.location_id', $location)
			->order_by('l.born_on', 'DESC')->order_by('l.id', 'DESC')->get()->result_array();

		$puppies = $this->db->select('p.*, s.deleted AS sale_deleted, s.suspended AS sale_suspended, s.customer_id, c.first_name, c.last_name')
			->from('shiba_puppies p')->join('shiba_litters l', 'l.id = p.litter_id')
			->join('sales s', 's.sale_id = p.sale_id AND s.location_id = l.location_id', 'left')
			->join('people c', 'c.person_id = s.customer_id', 'left')->where('l.location_id', $location)
			->order_by('p.code')->get()->result_array();
		$deliveries = $this->db->select('d.sale_id, d.estimated_delivery_or_pickup_date, d.actual_delivery_or_pickup_date')
			->from('sales_deliveries d')->join('shiba_puppies p', 'p.sale_id = d.sale_id')
			->join('shiba_litters l', 'l.id = p.litter_id')
			->join('sales s', 's.sale_id = p.sale_id AND s.location_id = l.location_id')
			->where('l.location_id', $location)->where('d.deleted', 0)->order_by('d.id', 'DESC')->get()->result_array();
		$by_sale = array();
		foreach ($deliveries as $delivery) {
			if (!isset($by_sale[$delivery['sale_id']])) {
				$by_sale[$delivery['sale_id']] = $delivery;
			}
		}
		$events = $this->db->select('e.*')->from('shiba_health_events e')
			->join('shiba_puppies p', 'p.id = e.puppy_id')->join('shiba_litters l', 'l.id = p.litter_id')
			->where('l.location_id', $location)->order_by('e.applied_on', 'DESC')->order_by('e.id', 'DESC')->get()->result_array();
		$health = array();
		foreach ($events as $event) {
			$health[$event['puppy_id']][] = $event;
		}

		$by_litter = array();
		$counts = array('available' => 0, 'reserved' => 0, 'delivered' => 0, 'review' => 0);
		foreach ($puppies as $puppy) {
			$delivery = isset($by_sale[$puppy['sale_id']]) ? $by_sale[$puppy['sale_id']] : array();
			$puppy['planned'] = empty($delivery['estimated_delivery_or_pickup_date'])
				? $puppy['delivery_on'] : substr($delivery['estimated_delivery_or_pickup_date'], 0, 10);
			$puppy['actual'] = empty($delivery['actual_delivery_or_pickup_date'])
				? $puppy['delivered_on'] : substr($delivery['actual_delivery_or_pickup_date'], 0, 10);
			$puppy['delivery_synced'] = (bool) $delivery;
			$puppy['state'] = $puppy['sale_id'] && ($puppy['sale_deleted'] || $puppy['sale_suspended'] || !$puppy['customer_id'])
				? 'review' : ($puppy['actual'] ? 'delivered' : ($puppy['sale_id'] ? 'reserved' : 'available'));
			$puppy['health'] = isset($health[$puppy['id']]) ? $health[$puppy['id']] : array();
			$counts[$puppy['state']]++;
			$by_litter[$puppy['litter_id']][] = $puppy;
		}
		return array('parents' => $parents, 'litters' => $litters, 'puppies' => $by_litter, 'counts' => $counts);
	}

	public function parent_exists($id, $sex, $location)
	{
		return $this->db->where(array('id' => $id, 'sex' => $sex, 'location_id' => $location))->count_all_results('shiba_parents') == 1;
	}

	public function litter($id, $location)
	{
		return $this->db->get_where('shiba_litters', array('id' => $id, 'location_id' => $location))->row_array();
	}

	public function puppy($id, $location)
	{
		return $this->db->select('p.*, l.born_on')->from('shiba_puppies p')->join('shiba_litters l', 'l.id = p.litter_id')
			->where(array('p.id' => $id, 'l.location_id' => $location))->get()->row_array();
	}

	public function receipt_details($sale_id)
	{
		$puppy = $this->db->select('p.code, p.pedigree_choice, p.delivery_on')
			->from('shiba_puppies p')->join('shiba_litters l', 'l.id = p.litter_id')
			->join('sales s', 's.sale_id = p.sale_id AND s.location_id = l.location_id')
			->where(array('s.sale_id' => (int) $sale_id, 's.deleted' => 0, 's.suspended' => 0))->get()->row_array();
		if ($puppy) {
			$delivery = $this->delivery_for_sale($sale_id);
			if (!empty($delivery['estimated_delivery_or_pickup_date'])) {
				$puppy['delivery_on'] = substr($delivery['estimated_delivery_or_pickup_date'], 0, 10);
			}
		}
		return $puppy;
	}

	public function delivery_for_sale($sale_id)
	{
		return $this->db->select('estimated_delivery_or_pickup_date, actual_delivery_or_pickup_date')
			->where(array('sale_id' => (int) $sale_id, 'deleted' => 0))
			->order_by('id', 'DESC')->limit(1)->get('sales_deliveries')->row_array();
	}

	public function sale($id, $location)
	{
		return $this->db->select('sale_id, customer_id')->get_where('sales', array(
			'sale_id' => $id, 'location_id' => $location, 'deleted' => 0, 'suspended' => 0, 'store_account_payment' => 0,
		))->row_array();
	}

	public function save($table, $data, $where = array())
	{
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		try {
			return $where ? $this->db->where($where)->update($table, $data) : $this->db->insert($table, $data);
		} finally {
			$this->db->db_debug = $db_debug;
		}
	}

	public function save_reservation($puppy, $data)
	{
		$where = array_intersect_key($puppy, array('id' => TRUE) + $data);
		$saved = $this->save('shiba_puppies', $data, $where);
		if (!$saved) {
			return FALSE;
		}
		if ($this->db->affected_rows()) {
			return TRUE;
		}
		// A retried request succeeds when the record already has those values.
		return (int) $this->db->where(array('id' => $puppy['id']) + $data)->count_all_results('shiba_puppies') === 1;
	}

	public function add_health($puppy_ids, $event)
	{
		$this->db->trans_begin();
		foreach ($puppy_ids as $id) {
			if (!$this->save('shiba_health_events', array('puppy_id' => $id) + $event)) {
				$this->db->trans_rollback();
				return FALSE;
			}
		}
		return $this->db->trans_commit();
	}
}
