<?php

/**
 * hutko API helper for VirtueMart.
 *
 * @package    VirtueMart
 * @subpackage Payment
 * @copyright  (C) 2026 hutko Service
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

/** Small, framework-independent Hutko protocol helper. */
final class HutkoApi
{
    public const CHECKOUT_REDIRECT_URL = 'https://pay.hutko.org/api/checkout/redirect/';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVERSED = 'reversed';

    public static function signature(array $data, $secretKey)
    {
        unset($data['signature'], $data['response_signature_string']);
        $data = array_filter($data, static function ($value) {
            return $value !== '' && $value !== null;
        });
        ksort($data, SORT_STRING);
        return sha1(implode('|', array_merge(array((string) $secretKey), array_map('strval', array_values($data)))));
    }

    public static function readCallback()
    {
        $post = \Joomla\CMS\Factory::getApplication()->input->post->getArray();
        if (!empty($post)) {
            return $post;
        }
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : array();
    }

    public static function isValidCallback(array $callback, $merchantId, $secretKey)
    {
        if (empty($callback['signature']) || empty($callback['merchant_id']) || empty($callback['order_status'])) {
            return false;
        }
        if ((string) $callback['merchant_id'] !== (string) $merchantId) {
            return false;
        }
        return hash_equals((string) $callback['signature'], self::signature($callback, $secretKey));
    }

    public static function matchesOrder(array $callback, array $order, $currency)
    {
        $expectedAmount = self::amountFromOrder($order);
        return isset($callback['amount'], $callback['currency'])
            && (int) $callback['amount'] === $expectedAmount
            && strtoupper((string) $callback['currency']) === strtoupper((string) $currency);
    }

    public static function amountFromOrder(array $order)
    {
        return (int) round((float) $order['details']['BT']->order_total * 100);
    }
}
