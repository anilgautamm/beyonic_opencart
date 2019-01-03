<?php

class ModelExtensionPaymentBeyonic extends Model {

    public function getMethod($address, $total) {
        $this->load->language('extension/payment/beyonic');
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE geo_zone_id = '" . (int) $this->config->get('beyonic_geo_zone_id') . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");
        if ($this->config->get('beyonic_total') > 0 && $this->config->get('beyonic_total') > $total) {
            $status = false;
        } else {
            $status = true;
        }
        $method_data = array();
        $add_disc = $this->config->get('payment_beyonic_desc') . "<a target='_blank' href='https://beyonic.com'> Powered by Beyonic payments </a>";
        if ($status) {
            $method_data = array(
                'code' => 'beyonic',
                'title' => $this->language->get('text_title'),
                'terms' => $add_disc,
                'sort_order' => $this->config->get('beyonic_sort_order')
            );
        }
        return $method_data;
    }

    public function editSetting($code, $key, $value, $store_id = 0) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int) $store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $key . "', `value` = '" . $this->db->escape($value) . "'");
    }

    public function addOrder($order_info, $transaction_id) {

        $this->db->query("INSERT INTO `" . DB_PREFIX . "beyonic_order` SET "
                . "`order_id` = '" . (int) $order_info['order_id'] . "',"
                . " `transaction_id` = '" . $transaction_id . "',"
                . "  `date_added` = now(),"
                . " `date_modified` = now(),"
                . "  `currency_code` = '" . $this->db->escape($order_info['currency_code']) . "',"
                . " `total` = '" . $this->currency->format($order_info['total'], $order_info['currency_code'], false, false) . "'");
        return $this->db->getLastId();
    }

    public function addTransaction($beyonic_order_id, $transaction_id, $order_info, $type, $note) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "beyonic_order_transaction` SET"
                . " `beyonic_order_id` = '" . (int) $beyonic_order_id . "',"
                . " `date_added` = now(),"
                . "`beyonic_transaction_id` = '" . $transaction_id . "',"
                . " `type` = '" . $this->db->escape($type) . "',"
                . " `amount` = '" . $this->currency->format($order_info['total'], $order_info['currency_code'], false, false) . "',"
                . "`note` = '" . $this->db->escape($note) . "'");
    }

    public function updateTransaction($order_id, $type, $note) {
        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "beyonic_order` WHERE `order_id` = '" . (int) $order_id . "' LIMIT 1");
        if ($qry->num_rows) {
            $order = $qry->row;
            $this->db->query("UPDATE `" . DB_PREFIX . "beyonic_order_transaction` SET"
                    . " `type` = '" . $this->db->escape($type) . "',"
                    . "`note` = '" . $this->db->escape($note) . "' WHERE beyonic_order_id = '" . $order['beyonic_order_id'] . "'");
        }
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

}
