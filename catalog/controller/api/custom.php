<?php

include_once 'admin/model/sale/order.php';
include_once 'admin/model/localisation/tax_rate.php';

class ControllerApiCustom extends Controller
{

    const limit = 15;

    private $data = [
        'data' => [],
        'meta' => []
    ];

    public function auth()
    {
        // load model
        $this->load->model('catalog/product');

        if (!isset($this->session->data['api_id'])) {
            unset($this->data['data'], $this->data['meta']);
            $this->data['message'] = $this->language->get('error_permission');

            return false;
        }

        return true;

    }

    /**
     * Tax list
     */
    public function tax()
    {
        if ($this->auth()) {
            $taxes = (new ModelLocalisationTaxRate($this->registry))->getTaxRates();
            $this->setData($taxes);
        }
    }

    public function order()
    {
        if ($this->auth()) {
            $order  =   new ModelSaleOrder($this->registry);
            $orders =   $this->getOrders($_GET);
            $orders =   $this->paginate($orders);
            $orders =   array_map(function($aorder) use ($order) {
                $aorder['custom_field'] =   unserialize($order->custom_field);
                $aorder['payment_custom_field'] =   unserialize($order->payment_custom_field);
                $aorder['products'] =   $order->getOrderProducts($aorder['order_id']);
                $aorder['totals']   =   $order->getOrderTotals($aorder['order_id']);
                return $aorder;
            }, $orders);
            $this->setData($orders);
        }

    }

    /**
     * Product list
     */
    public function product()
    {
        if ($this->auth()) {
            // get products
            $products = $this->model_catalog_product->getProducts();
            $products = $this->paginate($products);
            //Costoomize start
            $products = array_map(function ($data) {
                unset($data['description']);
                return $data;
            }, $products);
            //Customize end
            $this->setData($products);
        }
    }

    /**
     * Add data to response
     * @param $data
     */
    private function setData($data)
    {
        $this->data['data'] = $data;
    }

    /**
     * Paginate result set
     * @param $results
     * @param null $page
     * @param null $limit
     * @return array
     */
    private function paginate($results, $page = null, $limit = null)
    {
        if ($page == null) $page = max(@$_GET['page'], 1);
        if ($limit == null) $limit = isset($_GET['limit']) ?: self::limit;

        $paginate['total']          =   count($results);
        $paginate['current_page']   =   $page;
        $paginate['per_page']       =   $limit;
        $paginate['total_pages']    =   ceil(count($results) / $limit);

        $this->data['meta']['pagination'] = $paginate;

        return array_slice($results, ($page - 1) * $limit, $limit);
    }

    private function getOrders($data = array()) {

        $sql = "SELECT o.*, (SELECT os.name FROM " . DB_PREFIX . "order_status os WHERE os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') AS status, o.shipping_code, o.total, o.currency_code, o.currency_value, o.date_added, o.date_modified FROM `" . DB_PREFIX . "order` o";

        if (isset($data['filter_order_status'])) {
            $implode = array();

            $order_statuses = explode(',', $data['filter_order_status']);

            foreach ($order_statuses as $order_status_id) {
                $implode[] = "o.order_status_id = '" . (int)$order_status_id . "'";
            }

            if ($implode) {
                $sql .= " WHERE (" . implode(" OR ", $implode) . ")";
            } else {

            }
        } else {
            $sql .= " WHERE o.order_status_id > '0'";
        }

        if (!empty($data['filter_order_id'])) {
            $sql .= " AND o.order_id = '" . (int)$data['filter_order_id'] . "'";
        }

        if (!empty($data['filter_customer'])) {
            $sql .= " AND CONCAT(o.firstname, ' ', o.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'";
        }

        if (!empty($data['filter_date_added'])) {
            $sql .= " AND DATE(o.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
        }

        if (!empty($data['filter_date_modified'])) {
            $sql .= " AND DATE(o.date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
        }

        if (!empty($data['filter_total'])) {
            $sql .= " AND o.total = '" . (float)$data['filter_total'] . "'";
        }

        $sort_data = array(
            'o.order_id',
            'customer',
            'status',
            'o.date_added',
            'o.date_modified',
            'o.total'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY o.order_id";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * Echo response
     */
    private function response()
    {
        if (isset($this->request->server['HTTP_ORIGIN'])) {
            $this->response->addHeader('Access-Control-Allow-Origin: ' . $this->request->server['HTTP_ORIGIN']);
            $this->response->addHeader('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
            $this->response->addHeader('Access-Control-Max-Age: 1000');
            $this->response->addHeader('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($this->data));
    }

    public function __destruct()
    {
        $this->response();
    }
}