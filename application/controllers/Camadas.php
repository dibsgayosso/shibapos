<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'controllers/Secure_area.php';

class Camadas extends Secure_area
{
	private $location;
	private $can_edit;
	private $csrf;

	public function __construct()
	{
		parent::__construct('sales');
		$this->load->model('Shiba_kennel');
		$this->load->helper(array('form', 'text'));
		$this->location = (int) $this->Employee->get_logged_in_employee_current_location_id();
		$employee = $this->Employee->get_logged_in_employee_info()->person_id;
		$this->can_edit = $this->Employee->has_module_action_permission('sales', 'edit_sale', $employee, $this->location);
		$this->csrf = $this->session->userdata('shiba_kennel_csrf');
		if (!$this->csrf) {
			$this->csrf = bin2hex(random_bytes(32));
			$this->session->set_userdata('shiba_kennel_csrf', $this->csrf);
		}
		if (!$this->db->table_exists('shiba_health_events')) {
			show_error('Actualiza la base de datos con la migración de Camadas antes de abrir este panel.', 503);
		}
	}

	public function index()
	{
		$data = $this->Shiba_kennel->dashboard($this->location);
		$data['can_edit'] = $this->can_edit;
		$data['csrf'] = $this->csrf;
		$data['notice'] = $this->session->flashdata('shiba_kennel_notice');
		$sale_id = filter_var($this->input->get('venta'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		$data['sale_hint'] = $sale_id && $this->can_edit ? $this->Shiba_kennel->sale($sale_id, $this->location) : NULL;
		$this->load->view('camadas/dashboard', $data);
	}

	public function registrar_padre()
	{
		$this->require_write();
		$name = $this->field('name', 120);
		$sex = $this->field('sex', 1);
		if (!$name || !in_array($sex, array('M', 'H'), TRUE)) {
			$this->finish(FALSE, 'Completa el nombre y el sexo del ejemplar.');
		}
		$saved = $this->Shiba_kennel->save('shiba_parents', array(
			'location_id' => $this->location, 'name' => $name, 'sex' => $sex,
			'pedigree' => $this->field('pedigree', 100) ?: NULL, 'microchip' => $this->field('microchip', 40) ?: NULL,
		));
		$this->finish($saved, $saved ? 'Ejemplar registrado.' : 'No se pudo registrar el ejemplar.');
	}

	public function registrar_camada()
	{
		$this->require_write();
		$code = $this->field('code', 40);
		$born_on = $this->date('born_on');
		$sire = $this->integer('sire_id');
		$dam = $this->integer('dam_id');
		if (!$code || !$born_on || $born_on > date('Y-m-d')
			|| !$this->Shiba_kennel->parent_exists($sire, 'M', $this->location)
			|| !$this->Shiba_kennel->parent_exists($dam, 'H', $this->location)) {
			$this->finish(FALSE, 'Elige al padre y a la madre de esta sucursal e indica la fecha de nacimiento.');
		}
		$saved = $this->Shiba_kennel->save('shiba_litters', array(
			'location_id' => $this->location, 'code' => $code, 'born_on' => $born_on, 'sire_id' => $sire, 'dam_id' => $dam,
		));
		$this->finish($saved, $saved ? 'Camada registrada.' : 'No se pudo guardar. Verifica que el folio de camada no esté repetido.');
	}

	public function registrar_cachorro()
	{
		$this->require_write();
		$litter = $this->Shiba_kennel->litter($this->integer('litter_id'), $this->location);
		$code = $this->field('code', 40);
		$sex = $this->field('sex', 1);
		$coat = $this->field('coat', 80);
		if (!$litter || !$code || !$coat || !in_array($sex, array('M', 'H'), TRUE)) {
			$this->finish(FALSE, 'Completa el folio, la camada, el sexo y el manto del cachorro.');
		}
		$saved = $this->Shiba_kennel->save('shiba_puppies', array(
			'litter_id' => $litter['id'], 'code' => $code, 'sex' => $sex, 'coat' => $coat,
			'microchip' => $this->field('microchip', 40) ?: NULL,
		));
		$this->finish($saved, $saved ? 'Cachorro registrado.' : 'No se pudo guardar. Verifica el folio del cachorro y el microchip.', $litter['id']);
	}

	public function guardar_reserva()
	{
		$this->require_write();
		$puppy = $this->Shiba_kennel->puppy($this->integer('puppy_id'), $this->location);
		$sale_id = $this->integer('sale_id');
		$sale = $sale_id ? $this->Shiba_kennel->sale($sale_id, $this->location) : NULL;
		$choice = $this->field('pedigree_choice', 12);
		$pedigree = $this->field('pedigree_status', 12);
		$planned = $this->date('delivery_on');
		$actual = $this->date('delivered_on');
		if (!$puppy || ($this->field('sale_id', 20) !== '' && (empty($sale['customer_id'])))
			|| !in_array($choice, array('pending', 'with', 'without'), TRUE)
			|| !in_array($pedigree, array('pending', 'processing', 'issued', 'delivered'), TRUE)
			|| ($planned && $planned < $puppy['born_on'])
			|| ($actual && (!$sale || $actual < $puppy['born_on'] || $actual > date('Y-m-d')))
			|| (!$sale && ($planned || $choice !== 'pending'))
			|| ($puppy['sale_id'] && $sale_id && $sale_id !== (int) $puppy['sale_id'])) {
			$this->finish(FALSE, 'Verifica el folio de venta de esta sucursal, el cliente y las fechas. Para cambiar de venta, primero libera la reserva.');
		}

		$success = $this->Shiba_kennel->save_reservation($puppy, array(
			'sale_id' => $sale_id ?: NULL, 'pedigree_choice' => $choice,
			'pedigree_status' => $choice === 'with' ? $pedigree : NULL,
			'delivery_on' => $planned, 'delivered_on' => $actual,
		));
		$this->finish($success, $success ? 'Reserva y entrega actualizadas.' : 'No se pudo actualizar. El folio puede estar ocupado o la ficha cambió mientras la editabas.', $puppy['litter_id']);
	}

	public function registrar_salud()
	{
		$this->require_write();
		$litter = $this->Shiba_kennel->litter($this->integer('litter_id'), $this->location);
		$selected = $this->input->post('puppy_ids');
		$kind = $this->field('kind', 16);
		$product = $this->field('product', 120);
		$applied = $this->date('applied_on');
		$next = $this->date('next_on');
		if (!$litter || !is_array($selected) || !$selected || count($selected) > 100
			|| !in_array($kind, array('vaccine', 'deworming'), TRUE) || !$product
			|| !$applied || $applied < $litter['born_on'] || $applied > date('Y-m-d') || ($next && $next < $applied)) {
			$this->finish(FALSE, 'Selecciona los cachorros y verifica el producto y las fechas.');
		}
		$ids = array();
		foreach ($selected as $id) {
			$puppy = $this->Shiba_kennel->puppy((int) $id, $this->location);
			if (!$puppy || (int) $puppy['litter_id'] !== (int) $litter['id']) {
				$this->finish(FALSE, 'Todos los cachorros deben pertenecer a la camada seleccionada.');
			}
			$ids[(int) $puppy['id']] = (int) $puppy['id'];
		}
		$saved = $this->Shiba_kennel->add_health($ids, array(
			'kind' => $kind, 'applied_on' => $applied, 'product' => $product,
			'lot' => $this->field('lot', 80), 'veterinarian' => $this->field('veterinarian', 120) ?: NULL,
			'next_on' => $next,
		));
		$this->finish($saved, $saved ? 'Historial sanitario actualizado.' : 'No se guardó la jornada. Verifica si alguno de los registros ya existe.', $litter['id']);
	}

	private function require_write()
	{
		if (!$this->can_edit || $this->input->method(TRUE) !== 'POST'
			|| !hash_equals($this->csrf, $this->field('kennel_csrf', 64))) {
			show_error('La solicitud de Camadas no está autorizada.', 403);
		}
	}

	private function field($name, $limit)
	{
		$value = $this->input->post($name);
		$value = is_string($value) ? trim($value) : '';
		if (strlen($value) > $limit * 4 || !preg_match('//u', $value) || mb_strlen($value, 'UTF-8') > $limit) {
			$this->finish(FALSE, 'Uno de los datos ingresados supera la longitud permitida o contiene caracteres inválidos.');
		}
		return $value;
	}

	private function integer($name)
	{
		return (int) filter_var($this->field($name, 20), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
	}

	private function date($name)
	{
		$value = $this->field($name, 10);
		if ($value === '') {
			return NULL;
		}
		$date = DateTime::createFromFormat('!Y-m-d', $value);
		if (!$date || $date->format('Y-m-d') !== $value) {
			$this->finish(FALSE, 'Escribe una fecha válida.');
		}
		return $value;
	}

	private function finish($success, $message, $litter_id = NULL)
	{
		$this->session->set_flashdata('shiba_kennel_notice', array(
			'ok' => $success,
			'message' => $message,
		));
		redirect('camadas' . ($litter_id ? '#camada-' . (int) $litter_id : ''), 'location', 303);
		exit;
	}
}
