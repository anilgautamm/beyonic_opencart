<?php

class ControllerExtensionPaymentBeyonic extends Controller {

    private $error = array();

    public function index() {

        $this->load->language('extension/payment/beyonic');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_beyonic', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['api_key'])) {
            $data['error_api_key'] = $this->error['api_key'];
        } else {
            $data['error_api_key'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/beyonic', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['action'] = $this->url->link('extension/payment/beyonic', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_beyonic_api_key'])) {
            $data['payment_beyonic_api_key'] = $this->request->post['payment_beyonic_api_key'];
        } else {
            $data['payment_beyonic_api_key'] = $this->config->get('payment_beyonic_api_key');
        }
        if (isset($this->request->post['payment_beyonic_desc'])) {
            $data['payment_beyonic_desc'] = $this->request->post['payment_beyonic_desc'];
        } elseif ($this->config->get('payment_beyonic_desc')) {
            $data['payment_beyonic_desc'] = $this->config->get('payment_beyonic_desc');
        } else {
            $data['payment_beyonic_desc'] = 'Use mobile money from MPESA, MTN, AIRTEL or other networks to pay for your order!';
        }

        if (isset($this->request->post['payment_beyonic_desc2'])) {
            $data['payment_beyonic_desc2'] = $this->request->post['payment_beyonic_desc2'];
        } elseif ($this->config->get('payment_beyonic_desc2')) {
            $data['payment_beyonic_desc2'] = $this->config->get('payment_beyonic_desc2');
        } else {
            $data['payment_beyonic_desc2'] = 'You have choose Beyonic Mobile Payments.The payment will be completed on your phone number :';
        }

        if (isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1'))) {
            $data['payment_beyonic_ipn'] = HTTPS_CATALOG . 'index.php?route=extension/payment/beyonic/ipn/';
        } else {
            $data['payment_beyonic_ipn'] = HTTP_CATALOG . 'index.php?route=extension/payment/beyonic/ipn/';
        }

        if (isset($this->request->post['payment_beyonic_total'])) {
            $data['payment_beyonic_total'] = $this->request->post['payment_beyonic_total'];
        } else {
            $data['payment_beyonic_total'] = $this->config->get('payment_beyonic_total');
        }

        if (isset($this->request->post['payment_beyonic_status'])) {
            $data['payment_beyonic_status'] = $this->request->post['payment_beyonic_status'];
        } else {
            $data['payment_beyonic_status'] = $this->config->get('payment_beyonic_status');
        }

        if (isset($this->request->post['payment_beyonic_sort_order'])) {
            $data['payment_beyonic_sort_order'] = $this->request->post['payment_beyonic_sort_order'];
        } else {
            $data['payment_beyonic_sort_order'] = $this->config->get('payment_beyonic_sort_order');
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/beyonic', $data));
    }

    public function install() {
        $this->load->model('extension/payment/beyonic');
        $this->model_extension_payment_beyonic->install();
    }

    public function uninstall() {
        $this->load->model('extension/payment/beyonic');
        $this->load->library('Beyonic');
        Beyonic::setApiKey($this->config->get('payment_beyonic_api_key'));
        Beyonic_Webhook::delete($this->config->get('payment_beyonic_received_webhook_id'));
        $this->model_extension_payment_beyonic->uninstall();
    }

    public function order() {
        if ($this->config->get('payment_beyonic_status')) {
            $this->load->model('extension/payment/beyonic');

            $beyonic_order = $this->model_extension_payment_beyonic->getOrder($this->request->get['order_id']);

            if (!empty($beyonic_order)) {
                $this->load->language('extension/payment/beyonic');

                $beyonic_order['total_released'] = $this->model_extension_payment_beyonic->getTotalReleased($beyonic_order['beyonic_order_id']);

                $beyonic_order['total_formatted'] = $this->currency->format($beyonic_order['total'], $beyonic_order['currency_code'], false, false);
                $beyonic_order['total_released_formatted'] = $this->currency->format($beyonic_order['total_released'], $beyonic_order['currency_code'], false, false);
                $data['beyonic_order'] = $beyonic_order;
                $data['order_id'] = $this->request->get['order_id'];
                $data['token'] = $this->request->get['user_token'];

                return $this->load->view('extension/payment/beyonic_order', $data);
            }
        }
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/beyonic')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_beyonic_api_key']) {
            $this->error['api_key'] = $this->language->get('error_api_key');
        }
        return !$this->error;
    }

    public function callback() {
        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode($this->request->get));
    }

}
