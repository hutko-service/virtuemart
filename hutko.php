<?php
/**
 * hutko hosted-checkout payment plugin for VirtueMart.
 *
 * @package    VirtueMart
 * @subpackage Payment
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

if (!class_exists('vmPSPlugin')) {
    require JPATH_VM_PLUGINS . DIRECTORY_SEPARATOR . 'vmpsplugin.php';
}

require_once __DIR__ . '/lib/HutkoApi.php';

class plgVmPaymentHutko extends vmPSPlugin
{
    private const ORDER_SEPARATOR = ':';

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        Factory::getLanguage()->load('plg_vmpayment_hutko', JPATH_ADMINISTRATOR, null, true);
        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';

        $varsToPush = array(
            'payment_logos' => array('', 'char'),
            'countries' => array(0, 'int'),
            'payment_currency' => array(0, 'int'),
            'merchant_id' => array('', 'string'),
            'secret_key' => array('', 'string'),
            'currency' => array('UAH', 'string'),
            'status_success' => array('C', 'char'),
            'status_pending' => array('P', 'char'),
            'status_canceled' => array('X', 'char'),
        );
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    protected function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Hutko payment table');
    }

    public function getTableSQLFields()
    {
        return array(
            'id' => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(64) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\'',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2) DEFAULT NULL',
            'cost_percent_total' => 'decimal(10,2) DEFAULT NULL',
            'tax_id' => 'smallint(11) DEFAULT NULL',
        );
    }

    public function plgVmConfirmedOrder($cart, $order)
    {
        $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
        if (!$method || !$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        $orderNumber = (string) $order['details']['BT']->order_number;
        $paymentMethodId = (int) $order['details']['BT']->virtuemart_paymentmethod_id;
        $language = strtolower(substr(Factory::getLanguage()->getTag(), 0, 2));
        if (!in_array($language, array('uk', 'ru', 'en'), true)) {
            $language = 'en';
        }

        $responseUrl = Route::_(Uri::root() . 'index.php?option=com_virtuemart&view=orders&layout=details&order_number='
            . rawurlencode($orderNumber) . '&order_pass=' . rawurlencode((string) $order['details']['BT']->order_pass));
        $callbackUrl = Uri::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm='
            . $paymentMethodId;

        $request = array(
            'order_id' => $orderNumber . self::ORDER_SEPARATOR . time(),
            'merchant_id' => trim((string) $method->merchant_id),
            'order_desc' => Text::sprintf('VMPAYMENT_HUTKO_ORDER_DESCRIPTION', $orderNumber),
            'amount' => HutkoApi::amountFromOrder($order),
            'currency' => strtoupper((string) $method->currency),
            'server_callback_url' => $callbackUrl,
            'response_url' => $responseUrl,
            'lang' => $language,
            'sender_email' => (string) ($cart->BT['email'] ?? ''),
        );
        $request['signature'] = HutkoApi::signature($request, (string) $method->secret_key);

        $inputs = '';
        foreach ($request as $key => $value) {
            $inputs .= '<input type="hidden" name="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8')
                . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">';
        }

        $html = '<form action="' . htmlspecialchars(HutkoApi::CHECKOUT_REDIRECT_URL, ENT_QUOTES, 'UTF-8')
            . '" method="post" id="hutko_payment_form">' . $inputs . '</form>'
            . '<p>' . htmlspecialchars(Text::_('VMPAYMENT_HUTKO_REDIRECTING'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<script>document.getElementById("hutko_payment_form").submit();</script>';

        // VM 3.8.8+ reads this value on the order-complete page.
        if (class_exists('vRequest')) {
            vRequest::setVar('html', $html);
        }

        return $this->processConfirmedOrderPaymentResponse(
            true,
            $cart,
            $order,
            $html,
            $this->renderPluginName($method, $order),
            'P'
        );
    }

    /** Process the server-to-server Hutko callback. */
    public function plgVmOnPaymentNotification()
    {
        $callback = HutkoApi::readCallback();
        if (isset($callback['response']) && is_array($callback['response'])) {
            $callback = $callback['response'];
        }

        if (empty($callback['order_id']) || !is_string($callback['order_id'])) {
            return $this->callbackError(400);
        }

        list($orderNumber) = explode(self::ORDER_SEPARATOR, $callback['order_id'], 2);
        if ($orderNumber === '') {
            return $this->callbackError(400);
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php';
        }

        $orders = new VirtueMartModelOrders();
        $orderId = (int) $orders->getOrderIdByOrderNumber($orderNumber);
        if ($orderId <= 0) {
            return $this->callbackError(404);
        }

        $order = $orders->getOrder($orderId);
        $methodId = (int) $order['details']['BT']->virtuemart_paymentmethod_id;
        $method = $this->getVmPluginMethod($methodId);
        if (!$method || !$this->selectedThisElement($method->payment_element)) {
            return $this->callbackError(400);
        }

        if (!HutkoApi::isValidCallback($callback, (string) $method->merchant_id, (string) $method->secret_key)
            || !HutkoApi::matchesOrder($callback, $order, (string) $method->currency)) {
            return $this->callbackError(400);
        }

        $status = (string) $callback['order_status'];
        if ($status === HutkoApi::STATUS_APPROVED) {
            // Hutko retries callbacks. Do not generate duplicate status changes/emails.
            if ((string) $order['details']['BT']->order_status !== (string) $method->status_success) {
                $order['order_status'] = $method->status_success;
                $order['customer_notified'] = 1;
                $order['virtuemart_order_id'] = $orderId;
                $order['comments'] = 'Hutko payment ID: ' . (string) ($callback['payment_id'] ?? '')
                    . '; order ID: ' . (string) $callback['order_id'];
                $orders->updateStatusForOneOrder($orderId, $order, true);
            }
        } elseif (in_array($status, array(HutkoApi::STATUS_DECLINED, HutkoApi::STATUS_EXPIRED, HutkoApi::STATUS_REVERSED), true)) {
            $order['order_status'] = $method->status_canceled;
            $order['customer_notified'] = 1;
            $order['virtuemart_order_id'] = $orderId;
            $order['comments'] = 'Hutko status: ' . $status . '; payment ID: ' . (string) ($callback['payment_id'] ?? '');
            $orders->updateStatusForOneOrder($orderId, $order, true);
        }
        // created and processing callbacks are deliberately not treated as failures.

        http_response_code(200);
        echo 'OK';
        return true;
    }

    public function plgVmOnPaymentResponseReceived(&$html)
    {
        $method = $this->getVmPluginMethod(Factory::getApplication()->input->getInt('pm', 0));
        if (!$method || !$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        if (!class_exists('VirtueMartCart')) {
            require JPATH_VM_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cart.php';
        }
        VirtueMartCart::getCart()->emptyCart();
        return true;
    }

    /** Render the selected checkout logo at a template-safe size. */
    protected function displayLogos($logoList)
    {
        if (empty($logoList)) {
            return '';
        }

        $logos = is_array($logoList) ? $logoList : array($logoList);
        $baseUrl = Uri::root() . 'plugins/vmpayment/hutko/media/';
        $html = '';

        foreach ($logos as $logo) {
            $filename = basename((string) $logo);
            if ($filename === '') {
                continue;
            }
            $size = str_starts_with($filename, 'hutko_symbol_')
                ? 'max-width:40px;max-height:40px'
                : 'max-width:130px;max-height:32px';
            $html .= '<img src="' . htmlspecialchars($baseUrl . $filename, ENT_QUOTES, 'UTF-8')
                . '" alt="hutko" style="' . $size . ';width:auto;height:auto;vertical-align:middle" /> ';
        }

        return $html;
    }

    /** Hutko does not add a payment surcharge. */
    public function getCosts(VirtueMartCart $cart, $method, $cartPrices)
    {
        return 0;
    }

    /** Availability is controlled by the published VirtueMart payment method. */
    protected function checkConditions($cart, $method, $cartPrices)
    {
        return true;
    }

    public function plgVmgetPaymentCurrency($paymentMethodId, &$paymentCurrencyId)
    {
        $method = $this->getVmPluginMethod($paymentMethodId);
        if (!$method || !$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
        return true;
    }

    public function plgVmOnStoreInstallPaymentPluginTable($jpluginId) { return $this->onStoreInstallPluginTable($jpluginId); }
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) { return $this->OnSelectCheck($cart); }
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected, &$htmlIn) { return $this->displayListFE($cart, $selected, $htmlIn); }
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$cartPricesName) { return $this->onSelectedCalculatePrice($cart, $cartPrices, $cartPricesName); }
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cartPrices = array()) { return $this->onCheckAutomaticSelected($cart, $cartPrices); }
    public function plgVmOnShowOrderFEPayment($orderId, $methodId, &$paymentName) { $this->onShowOrderFE($orderId, $methodId, $paymentName); }
    public function plgVmonShowOrderPrintPayment($orderNumber, $methodId) { return $this->onShowOrderPrint($orderNumber, $methodId); }
    public function plgVmDeclarePluginParamsPayment($name, $id, &$data) { return $this->declarePluginParams('payment', $name, $id, $data); }
    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) { return $this->setOnTablePluginParams($name, $id, $table); }
    public function plgVmDeclarePluginParamsPaymentVM3(&$data) { return $this->declarePluginParams('payment', $data); }

    private function callbackError($status)
    {
        http_response_code($status);
        echo 'Invalid Hutko callback';
        return false;
    }
}
