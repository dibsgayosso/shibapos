<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_shiba_camadas extends MY_Migration
{
	public function up()
	{
		$parents = $this->db->protect_identifiers('shiba_parents', TRUE);
		$litters = $this->db->protect_identifiers('shiba_litters', TRUE);
		$puppies = $this->db->protect_identifiers('shiba_puppies', TRUE);
		$events = $this->db->protect_identifiers('shiba_health_events', TRUE);
		$sales = $this->db->protect_identifiers('sales', TRUE);

		foreach (array(
			"CREATE TABLE IF NOT EXISTS $parents (
				id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, location_id INT NOT NULL,
				name VARCHAR(120) NOT NULL, sex CHAR(1) NOT NULL, pedigree VARCHAR(100) NULL,
				microchip VARCHAR(40) NULL, INDEX (location_id, sex, name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"CREATE TABLE IF NOT EXISTS $litters (
				id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, location_id INT NOT NULL,
				code VARCHAR(40) NOT NULL, born_on DATE NOT NULL, sire_id INT NOT NULL, dam_id INT NOT NULL,
				UNIQUE (location_id, code), FOREIGN KEY (sire_id) REFERENCES $parents(id),
				FOREIGN KEY (dam_id) REFERENCES $parents(id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"CREATE TABLE IF NOT EXISTS $puppies (
				id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, litter_id INT NOT NULL,
				code VARCHAR(40) NOT NULL, sex CHAR(1) NOT NULL, coat VARCHAR(80) NOT NULL,
				microchip VARCHAR(40) NULL, sale_id INT NULL, pedigree_choice VARCHAR(12) NOT NULL DEFAULT 'pending',
				pedigree_status VARCHAR(12) NULL, delivery_on DATE NULL, delivered_on DATE NULL,
				UNIQUE (litter_id, code), UNIQUE (sale_id), UNIQUE (microchip),
				FOREIGN KEY (litter_id) REFERENCES $litters(id), FOREIGN KEY (sale_id) REFERENCES $sales(sale_id) ON DELETE SET NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"CREATE TABLE IF NOT EXISTS $events (
				id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, puppy_id INT NOT NULL,
				kind VARCHAR(16) NOT NULL, applied_on DATE NOT NULL, product VARCHAR(120) NOT NULL,
				lot VARCHAR(80) NOT NULL DEFAULT '', veterinarian VARCHAR(120) NULL, next_on DATE NULL,
				UNIQUE (puppy_id, kind, applied_on, product, lot),
				FOREIGN KEY (puppy_id) REFERENCES $puppies(id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
		) as $statement) {
			if (!$this->db->query($statement)) {
				throw new RuntimeException('No se pudo crear el módulo de Camadas Shiba.');
			}
		}
	}

	public function down()
	{
		// The kennel records are intentionally retained during a downgrade.
	}
}
