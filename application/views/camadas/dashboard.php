<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $this->load->view('partial/header'); ?>
<link rel="stylesheet" href="<?php echo H(base_url('css/shiba-camadas.css')); ?>">
<?php
$states = array('available' => 'Disponibles', 'reserved' => 'Reservados', 'delivered' => 'Entregados', 'review' => 'Por revisar');
$state_labels = array('available' => 'Disponible', 'reserved' => 'Reservado', 'delivered' => 'Entregado', 'review' => 'Revisar venta');
$choices = array('pending' => 'Por definir', 'with' => 'Con pedigree', 'without' => 'Sin pedigree');
$pedigrees = array('pending' => 'Pendiente', 'processing' => 'En trámite', 'issued' => 'Emitido', 'delivered' => 'Entregado');
$male = array_filter($parents, function ($parent) { return $parent['sex'] === 'M'; });
$female = array_filter($parents, function ($parent) { return $parent['sex'] === 'H'; });
$date_label = function ($date) { return $date ? implode('/', array_reverse(explode('-', $date))) : 'Por definir'; };
$open_form = function ($action) use ($csrf) {
	echo form_open('camadas/' . $action, array('class' => 'kc-form')) . form_hidden('kennel_csrf', $csrf);
};
?>
<section class="kc" aria-labelledby="kc-title">
	<header class="kc-head">
		<div>
			<div class="kc-eyebrow">Kimi Assad / Shiba POS</div>
			<h1 id="kc-title">Camadas y cachorros</h1>
			<p>Revisa el origen, la reserva, el historial sanitario y la entrega de cada cachorro.</p>
		</div>
		<?php if ($can_edit) { ?><a class="kc-btn kc-primary" href="#kc-alta">Registrar camada</a><?php } ?>
	</header>

	<?php if ($notice) { ?>
		<p class="kc-notice <?php echo $notice['ok'] ? 'kc-ok' : 'kc-error'; ?>" role="status"><?php echo H($notice['message']); ?></p>
	<?php } ?>
	<?php if (!empty($sale_hint['customer_id'])) { ?>
		<p class="kc-notice kc-ok">Venta <strong>#<?php echo H($sale_hint['sale_id']); ?></strong> seleccionada. Abre la ficha de un cachorro disponible y guarda la reserva.</p>
	<?php } ?>

	<div class="kc-stats">
		<div class="kc-metric-litters"><span>Camadas</span><strong><?php echo count($litters); ?></strong></div>
		<?php foreach ($states as $key => $label) { ?>
			<div class="kc-metric-<?php echo H($key); ?>"><span><?php echo H($label); ?></span><strong><?php echo (int) $counts[$key]; ?></strong></div>
		<?php } ?>
	</div>

	<nav class="kc-nav" aria-label="Secciones de camadas">
		<a href="#kc-camadas">Camadas</a><a href="#kc-padres">Catálogo de padres</a>
		<?php if ($can_edit) { ?><a href="#kc-alta">Nuevos registros</a><?php } ?>
	</nav>

	<section id="kc-camadas" class="kc-section">
		<div class="kc-section-header"><h2>Camadas</h2></div>
		<?php if (!$litters) { ?><p class="kc-empty">Registra al padre y a la madre para crear tu primera camada.</p><?php } ?>
		<?php foreach ($litters as $litter) { $members = isset($puppies[$litter['id']]) ? $puppies[$litter['id']] : array(); ?>
			<article class="kc-litter" id="camada-<?php echo (int) $litter['id']; ?>">
				<header class="kc-litter-head">
					<div><div class="kc-eyebrow">Nacimiento · <?php echo H($date_label($litter['born_on'])); ?></div>
						<h3><?php echo H($litter['code']); ?></h3>
						<p><strong>Padre</strong> <?php echo H($litter['sire']); ?> <span aria-hidden="true">·</span> <strong>Madre</strong> <?php echo H($litter['dam']); ?></p>
					</div>
					<span class="kc-count"><?php echo count($members); ?> <?php echo count($members) === 1 ? 'cachorro' : 'cachorros'; ?></span>
				</header>
				<?php if (!$members) { ?><p class="kc-empty">Esta camada aún no tiene cachorros registrados.</p><?php } ?>
				<?php foreach ($members as $puppy) { ?>
					<details class="kc-puppy">
						<summary>
							<span><strong><?php echo H($puppy['code']); ?></strong><small><?php echo $puppy['sex'] === 'M' ? 'Macho' : 'Hembra'; ?> · <?php echo H($puppy['coat']); ?></small></span>
							<span><small>Pedigree</small><?php echo H($choices[$puppy['pedigree_choice']]); ?></span>
							<span><small><?php echo $puppy['actual'] ? 'Entrega real' : 'Entrega acordada'; ?></small><?php echo H($date_label($puppy['actual'] ?: $puppy['planned'])); ?></span>
							<span class="kc-badge kc-<?php echo H($puppy['state']); ?>"><?php echo H($state_labels[$puppy['state']]); ?></span>
						</summary>
						<div class="kc-puppy-content">
							<div class="kc-columns">
								<section>
									<h4>Reserva y entrega</h4>
									<p>Cliente <strong><?php echo H($puppy['customer_id'] ? trim($puppy['first_name'] . ' ' . $puppy['last_name']) : 'Por asignar'); ?></strong>
										<?php if ($puppy['sale_id']) { ?> · <a href="<?php echo H(site_url('sales/receipt/' . (int) $puppy['sale_id'])); ?>">Venta #<?php echo (int) $puppy['sale_id']; ?></a><?php } ?>
									</p>
									<?php if ($puppy['microchip']) { ?><p>Microchip <?php echo H($puppy['microchip']); ?></p><?php } ?>
									<p>Pedigree <?php echo H($choices[$puppy['pedigree_choice']]); ?><?php if ($puppy['pedigree_status']) { ?> · <?php echo H($pedigrees[$puppy['pedigree_status']]); ?><?php } ?></p>
									<p>Acordada <?php echo H($date_label($puppy['planned'])); ?> · Real <?php echo H($date_label($puppy['actual'])); ?></p>
									<?php if ($puppy['delivery_synced']) { ?><p class="kc-help">Las fechas registradas en Entregas tienen prioridad en este panel.</p><?php } ?>
									<?php if ($can_edit) {
										$open_form('guardar_reserva');
										echo form_hidden('puppy_id', $puppy['id']);
										$selected_sale = $puppy['sale_id'] ?: (!empty($sale_hint['customer_id']) ? $sale_hint['sale_id'] : '');
									?>
										<label>Folio de venta <input name="sale_id" type="number" min="1" value="<?php echo H($selected_sale); ?>"></label>
										<label>Elección de pedigree <select name="pedigree_choice"><?php foreach ($choices as $key => $label) { ?><option value="<?php echo H($key); ?>" <?php echo $puppy['pedigree_choice'] === $key ? 'selected' : ''; ?>><?php echo H($label); ?></option><?php } ?></select></label>
										<label>Estado del pedigree <select name="pedigree_status"><?php foreach ($pedigrees as $key => $label) { ?><option value="<?php echo H($key); ?>" <?php echo $puppy['pedigree_status'] === $key ? 'selected' : ''; ?>><?php echo H($label); ?></option><?php } ?></select></label>
										<label>Entrega acordada <input name="delivery_on" type="date" value="<?php echo H($puppy['delivery_on']); ?>"></label>
										<label>Entrega real <input name="delivered_on" type="date" value="<?php echo H($puppy['delivered_on']); ?>"></label>
										<p class="kc-help"><?php echo $puppy['actual']
											? 'Este cachorro ya tiene entrega registrada y conserva su vínculo con la venta.'
											: 'Para liberar una reserva, vacía el folio y las fechas y elige Por definir.'; ?></p>
										<button class="kc-btn kc-primary">Guardar reserva</button>
									</form>
									<?php } ?>
								</section>
								<section>
									<h4>Historial sanitario</h4>
									<?php if (!$puppy['health']) { ?><p class="kc-help">Sin aplicaciones registradas.</p><?php } ?>
									<ul class="kc-health"><?php foreach ($puppy['health'] as $event) { ?>
										<li><small><?php echo H($date_label($event['applied_on'])); ?> · <?php echo $event['kind'] === 'vaccine' ? 'Vacunación' : 'Desparasitación'; ?></small>
											<strong><?php echo H($event['product']); ?></strong>
											<?php if ($event['lot']) { ?><span>Lote <?php echo H($event['lot']); ?></span><?php } ?>
											<?php if ($event['veterinarian']) { ?><span><?php echo H($event['veterinarian']); ?></span><?php } ?>
											<?php if ($event['next_on']) { ?><span>Próxima fecha indicada <?php echo H($date_label($event['next_on'])); ?></span><?php } ?>
										</li>
									<?php } ?></ul>
								</section>
							</div>
						</div>
					</details>
				<?php } ?>
				<?php if ($can_edit && $members) { ?>
					<details class="kc-batch">
						<summary>Registrar vacunación o desparasitación</summary>
						<?php $open_form('registrar_salud'); echo form_hidden('litter_id', $litter['id']); ?>
							<fieldset class="kc-who"><legend>Cachorros atendidos</legend>
								<?php foreach ($members as $member) { ?><label><input type="checkbox" name="puppy_ids[]" value="<?php echo (int) $member['id']; ?>"> <?php echo H($member['code']); ?></label><?php } ?>
							</fieldset>
							<label>Tipo <select name="kind"><option value="vaccine">Vacunación</option><option value="deworming">Desparasitación</option></select></label>
							<label>Fecha de aplicación <input name="applied_on" type="date" required></label>
							<label>Producto <input name="product" required maxlength="120"></label>
							<label>Lote <input name="lot" maxlength="80"></label>
							<label>Veterinario <input name="veterinarian" maxlength="120"></label>
							<label>Próxima fecha indicada <input name="next_on" type="date"></label>
							<button class="kc-btn kc-primary">Registrar aplicación</button>
						</form>
					</details>
				<?php } ?>
			</article>
		<?php } ?>
	</section>

	<section id="kc-padres" class="kc-section">
		<div class="kc-section-header"><h2>Catálogo de padres</h2></div>
		<?php if (!$parents) { ?><p class="kc-empty">Aún no hay ejemplares registrados.</p><?php } ?>
		<div class="kc-parent-grid"><?php foreach ($parents as $parent) { ?>
			<div class="kc-parent"><small><?php echo $parent['sex'] === 'M' ? 'Macho' : 'Hembra'; ?></small>
				<strong><?php echo H($parent['name']); ?></strong>
				<?php if ($parent['pedigree']) { ?><span>Pedigree <?php echo H($parent['pedigree']); ?></span><?php } ?>
				<?php if ($parent['microchip']) { ?><span>Microchip <?php echo H($parent['microchip']); ?></span><?php } ?>
				<?php foreach ($litters as $litter) {
					if ((int) $parent['id'] === (int) $litter['sire_id'] || (int) $parent['id'] === (int) $litter['dam_id']) { ?>
						<a href="#camada-<?php echo (int) $litter['id']; ?>"><?php echo H($litter['code']); ?></a>
					<?php }
				} ?>
			</div>
		<?php } ?></div>
	</section>

	<?php if ($can_edit) { ?>
		<section id="kc-alta" class="kc-section">
			<div class="kc-section-header"><h2>Nuevos registros</h2></div>
			<div class="kc-intake">
				<details><summary>Registrar padre o madre</summary>
					<?php $open_form('registrar_padre'); ?>
						<label>Nombre <input name="name" required maxlength="120"></label>
						<label>Sexo <select name="sex"><option value="M">Macho</option><option value="H">Hembra</option></select></label>
						<label>Número de pedigree <input name="pedigree" maxlength="100"></label>
						<label>Microchip <input name="microchip" maxlength="40"></label>
						<button class="kc-btn kc-primary">Registrar ejemplar</button>
					</form>
				</details>
				<details><summary>Registrar camada</summary>
					<?php $open_form('registrar_camada'); ?>
						<label>Folio de camada <input name="code" required maxlength="40"></label>
						<label>Nacimiento <input name="born_on" type="date" required></label>
						<label>Padre <select name="sire_id" required><option value="">Seleccionar</option><?php foreach ($male as $parent) { ?><option value="<?php echo (int) $parent['id']; ?>"><?php echo H($parent['name']); ?></option><?php } ?></select></label>
						<label>Madre <select name="dam_id" required><option value="">Seleccionar</option><?php foreach ($female as $parent) { ?><option value="<?php echo (int) $parent['id']; ?>"><?php echo H($parent['name']); ?></option><?php } ?></select></label>
						<button class="kc-btn kc-primary">Guardar camada</button>
					</form>
				</details>
				<details><summary>Registrar cachorro</summary>
					<?php $open_form('registrar_cachorro'); ?>
						<label>Camada <select name="litter_id" required><option value="">Seleccionar</option><?php foreach ($litters as $litter) { ?><option value="<?php echo (int) $litter['id']; ?>"><?php echo H($litter['code']); ?></option><?php } ?></select></label>
						<label>Folio individual <input name="code" required maxlength="40"></label>
						<label>Sexo <select name="sex"><option value="M">Macho</option><option value="H">Hembra</option></select></label>
						<label>Manto <input name="coat" required maxlength="80"></label>
						<label>Microchip <input name="microchip" maxlength="40"></label>
						<button class="kc-btn kc-primary">Guardar cachorro</button>
					</form>
				</details>
			</div>
		</section>
	<?php } ?>
</section>
<?php $this->load->view('partial/footer'); ?>
