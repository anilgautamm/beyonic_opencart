<?php

class ModelExtensionPaymentBeyonic extends Model {

    public function install() {
        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "beyonic_order` (
			  `beyonic_order_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `order_id` INT(11) NOT NULL,
			  `transaction_id` VARCHAR(50),
			  `date_added` DATETIME NOT NULL,
			  `date_modified` DATETIME NOT NULL,
			  `currency_code` CHAR(3) NOT NULL,
			  `total` DECIMAL( 10, 2 ) NOT NULL,
			  PRIMARY KEY (`beyonic_order_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "beyonic_order_transaction` (
			  `beyonic_order_transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `beyonic_order_id` INT(11) NOT NULL,
                          `beyonic_transaction_id` INT(11) NOT NULL,
			  `date_added` DATETIME NOT NULL,
			  `type` ENUM('success', 'pending', 'failed', 'cancelled','process') DEFAULT NULL,
			  `amount` DECIMAL( 10, 2 ) NOT NULL,
                          `note` varchar(255) NOT NULL,
			  PRIMARY KEY (`beyonic_order_transaction_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "beyonic_order`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "beyonic_order_transaction`;");
    }

    public function updateReleaseStatus($beyonic_order_id, $status) {
        $this->db->query("UPDATE `" . DB_PREFIX . "beyonic_order` SET `release_status` = '" . (int) $status . "' WHERE `beyonic_order_id` = '" . (int) $beyonic_order_id . "'");
    }

    public function updateTransactionId($beyonic_order_id, $transaction_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "beyonic_order` SET `transaction_id` = '" . (int) $transaction_id . "' WHERE `beyonic_order_id` = '" . (int) $beyonic_order_id . "'");
    }

    public function getOrder($order_id) {

        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "beyonic_order` WHERE `order_id` = '" . (int) $order_id . "' LIMIT 1");

        if ($qry->num_rows) {
            $order = $qry->row;
            $order['transactions'] = $this->getTransactions($order['beyonic_order_id']);

            return $order;
        } else {
            return false;
        }
    }

    private function getTransactions($beyonic_order_id) {
        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "beyonic_order_transaction` WHERE `beyonic_order_id` = '" . (int) $beyonic_order_id . "'");

        if ($qry->num_rows) {
            return $qry->rows;
        } else {
            return false;
        }
    }

    public function addTransaction($beyonic_order_id, $type, $total) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "beyonic_order_transaction` SET `beyonic_order_id` = '" . (int) $beyonic_order_id . "', `date_added` = now(), `type` = '" . $this->db->escape($type) . "', `amount` = '" . (float) $total . "'");
    }

    public function getTotalReleased($beyonic_order_id) {
        $query = $this->db->query("SELECT SUM(`amount`) AS `total` FROM `" . DB_PREFIX . "beyonic_order_transaction` WHERE `beyonic_order_id` = '" . (int) $beyonic_order_id . "' AND (`type` = 'sale' OR `type` = 'rebate')");

        return (float) $query->row['total'];
    }

    public function getTotalRebated($beyonic_order_id) {
        $query = $this->db->query("SELECT SUM(`amount`) AS `total` FROM `" . DB_PREFIX . "beyonic_order_transaction` WHERE `beyonic_order_id` = '" . (int) $beyonic_order_id . "' AND 'rebate'");

        return (float) $query->row['total'];
    }

    public function callback() {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($this->request->get));
    }

    public function logger($message) {
        if ($this->config->get('beyonic_debug') == 1) {
            $log = new Log('beyonic.log');
            $log->write($message);
        }
    }

}
