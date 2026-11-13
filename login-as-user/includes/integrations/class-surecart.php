<?php

if (!defined('WPINC')) {
    die;
}

class LoginAsUser_SureCart_Integration extends LoginAsUser_Integration_Abstract
{

    public function init()
    {
        /* orders list view */
        add_filter('manage_sc-orders_columns', [$this, 'loginasuser_col'], 10, 2);
        add_filter('manage_sc-orders_custom_column', [$this, 'loginasuser_col_content_order'], 10, 2);

        /* customers list view */
        add_filter('manage_sc-customers_columns', [$this, 'loginasuser_col'], 10, 2);
        add_filter('manage_sc-customers_custom_column', [$this, 'loginasuser_col_content'], 10, 2);

        /* SureCart 4.10+ renders both lists as a React DataViews app, so the column filters above never fire. */
        add_action('admin_enqueue_scripts', [$this, 'enqueueDataViewsScript']);
        add_action('wp_ajax_w357_login_as_user_surecart_buttons', [$this, 'ajaxDataViewsButtons']);
    }

    /**
     * Loads the script that adds the column to the SureCart DataViews lists.
     *
     * @return void
     */
    public function enqueueDataViewsScript()
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $types = ['sc-orders' => 'orders', 'sc-customers' => 'customers'];
        if (!isset($types[$page]) || !w357LoginAsUser::$pluginSettings->listConfiguration['surecart']['buttonDisplay']) {
            return;
        }

        $handle = 'w357-login-as-user-surecart-dataviews';
        wp_enqueue_script($handle, plugins_url('admin/js/surecart-dataviews.js', dirname(__DIR__, 2) . '/index.php'), [], LOGINASUSER_VERSION, true);
        wp_localize_script($handle, 'w357LoginAsUserSureCart', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('w357_login_as_user_surecart_buttons'),
            'type' => $types[$page],
            'columnTitle' => __('Login as User', 'login-as-user'),
        ]);
    }

    /**
     * Returns the button markup for the SureCart records visible in the DataViews list.
     *
     * @return void
     */
    public function ajaxDataViewsButtons()
    {
        check_ajax_referer('w357_login_as_user_surecart_buttons', 'nonce');

        $type = isset($_POST['type']) && 'customers' === $_POST['type'] ? 'customers' : 'orders';
        if (!current_user_can('edit_sc_' . $type)) {
            wp_send_json_error(null, 403);
        }

        $ids = isset($_POST['ids']) ? array_map('sanitize_text_field', (array)wp_unslash($_POST['ids'])) : [];
        $ids = array_slice(array_unique(array_filter($ids, function ($id) {
            return preg_match('/^[\w-]{1,64}$/', $id);
        })), 0, 100);

        $customerIds = array_fill_keys($ids, null);
        

        if ('orders' === $type) {
            $this->loginAsUserButtonGenerator->messages['noUserAvailable'] = __('No user exists for this order.', 'login-as-user');
        }

        $html = [];
        foreach ($customerIds as $id => $customerId) {
            $html[$id] = wp_kses($this->buttonHtml($customerId), LoginAsUserButton_Generator::allowedHtml());
        }
        wp_send_json_success($html);
    }

    /**
     * @param $columns
     * @return array
     */
    public function loginasuser_col($columns)
    {
        if (!w357LoginAsUser::$pluginSettings->listConfiguration['surecart']['buttonDisplay']) {
            return $columns;
        }

        $columns['loginasuser_col'] = __('Login as User', 'login-as-user');
        return $columns;
    }

    /**
     * @param string $column
     * @param \SureCart\Models\Order $orderDB
     * @return void
     */
    public function loginasuser_col_content_order($column, $orderDB)
    {
        $this->loginAsUserButtonGenerator->messages['noUserAvailable'] = __('No user exists for this order.', 'login-as-user');
        $this->loginasuser_col_content($column, isset($orderDB->checkout->customer) ? $orderDB->checkout->customer : null);
    }

    /**
     * @param string $column
     * @param \SureCart\Models\Customer $customerDB
     * @return void
     */
    public function loginasuser_col_content($column, $customerDB)
    {
        if ('loginasuser_col' === $column) {
            $this->echoHtml($this->buttonHtml($customerDB && isset($customerDB->id) ? $customerDB->id : null));
        }
    }

    /**
     * @param string|null $customerId SureCart customer id.
     * @return string
     */
    private function buttonHtml($customerId)
    {
        

         
        return $this->main->onlyInProTextLink();
         
    }

}