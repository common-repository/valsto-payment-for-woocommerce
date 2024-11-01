<?php
/**
 * Plugin Name: Valsto Payment for WooCommerce
 * Plugin URI: https://www.wordpress.org/plugins/valsto-payment-for-woocommerce/
 * Description: Easily accept Valsto payments on your WordPress / WooCommerce website.
 * Author: Valsto Inc
 * Author URI: https://www.valsto.com/
 * Version: 1.0.0
 *
 * License
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Required minimums
 */
define( 'WC_VALSTO_VBUTTON_MIN_PHP_VER', '5.4.0' );
define( 'WC_VALSTO_VBUTTON_SETTINGS', 'woocommerce_valsto_payment_settings');
define( 'WC_VALSTO_VBUTTON_VALSTO_API_HOME', 'https://qa.api.valsto.com' );
define( 'WC_VALSTO_VBUTTON_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_VALSTO_VBUTTON_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_VALSTO_VBUTTON_CONTEXT_URL', explode(site_url() . '/' , WC_VALSTO_VBUTTON_PLUGIN_URL)[1]);
define( 'WC_VALSTO_VBUTTON_VALIDATE_ACTION', '/validate-vpayment');
define( 'WC_VALSTO_VBUTTON_INIT_ACTION', '/init-vpayment');
define( 'WC_VALSTO_VBUTTON_PREPARE_PURCHASE_ACTION', '/prepare-purchase');
define( 'WC_VALSTO_VBUTTON_WEBHOOK_ACTION', '/webhook');
define( 'WC_VALSTO_VBUTTON_VALIDATE_PAYMENT_URL', WC_VALSTO_VBUTTON_PLUGIN_URL . WC_VALSTO_VBUTTON_VALIDATE_ACTION);
define( 'WC_VALSTO_VBUTTON_INIT_URL', WC_VALSTO_VBUTTON_PLUGIN_URL . WC_VALSTO_VBUTTON_INIT_ACTION);
define( 'WC_VALSTO_VBUTTON_PREPARE_PURCHASE_URL', WC_VALSTO_VBUTTON_PLUGIN_URL . WC_VALSTO_VBUTTON_PREPARE_PURCHASE_ACTION);
define( 'WC_VALSTO_VBUTTON_WEBHOOK_URL', WC_VALSTO_VBUTTON_PLUGIN_URL . WC_VALSTO_VBUTTON_WEBHOOK_ACTION);
define( 'WC_VALSTO_VBUTTON_VALIDATE_PAYMENT_CONTEXT', WC_VALSTO_VBUTTON_CONTEXT_URL . WC_VALSTO_VBUTTON_VALIDATE_ACTION);
define( 'WC_VALSTO_VBUTTON_INIT_CONTEXT', WC_VALSTO_VBUTTON_CONTEXT_URL . WC_VALSTO_VBUTTON_INIT_ACTION);
define( 'WC_VALSTO_VBUTTON_PREPARE_PURCHASE_CONTEXT', WC_VALSTO_VBUTTON_CONTEXT_URL . WC_VALSTO_VBUTTON_PREPARE_PURCHASE_ACTION);
define( 'WC_VALSTO_VBUTTON_WEBHOOK_CONTEXT', WC_VALSTO_VBUTTON_CONTEXT_URL . WC_VALSTO_VBUTTON_WEBHOOK_ACTION);

class WC_Valsto_VButton_Loader
{
    const PLUGIN_NAME_SPACE ="woocommerce-valsto-vpayment";
    
    /**
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $instance;
    
    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance()
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone() {}
    
    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup() {}
    
    /** @var whether or not we need to load code for / support subscriptions */
    private $subscription_support_enabled = false;
    
    /**
     * Notices (array)
     * @var array
     */
    public $notices = array();
    
    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
        add_action( 'admin_init', array( $this, 'check_environment' ) );
        add_action( 'parse_request', array( $this, 'parse_custom_request' ) );
        
        // admin_notices is prioritized later to allow concrete classes to use admin_notices to push entries to the notices array
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
        
        // Don't hook anything else in the plugin if we're in an incompatible environment
        if ( self::get_environment_warning() ) {
            return;
        }
        
        add_action( 'plugins_loaded', array( $this, 'init_gateways' ), 0 );
        
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
        add_action( 'woocommerce_available_payment_gateways', array( $this, 'possibly_disable_other_gateways' ) );
    }
    
    /**
     * Adds the custom actions for init the vPayment transaction.
     */
    public function parse_custom_request($query)
    {
        if(isset($query->request)){
            switch ($query->request) {
                case WC_VALSTO_VBUTTON_VALIDATE_PAYMENT_CONTEXT:
                    set_error_handler(array($this, 'throwErrorException'));
                    $this->validateVPayment();
                    exit();
                    break;
                case WC_VALSTO_VBUTTON_INIT_CONTEXT:
                    set_error_handler(array($this, 'throwErrorException'));
                    $this->initVPayment();
                    exit();
                    break;
                case WC_VALSTO_VBUTTON_PREPARE_PURCHASE_CONTEXT:
                    set_error_handler(array($this, 'throwErrorException'));
                    $this->preparePurchase();
                    exit();
                    break;
                case WC_VALSTO_VBUTTON_WEBHOOK_CONTEXT:
                    set_error_handler(array($this, 'throwErrorException'));
                    $this->webhook();
                    exit();
                    break;
                default:
                    break;
            }
        }
    }
    
    /**
     * Validates ant inits the vPayment Process
     */
    protected function validateVPayment()
    {
        try {
            $postData = [];
            $settings = (object) get_option(WC_VALSTO_VBUTTON_SETTINGS, array());
            
            $settings->enabled = ($settings->enabled === 'yes') ? true : false;
            $settings->debug = ($settings->debug === 'yes') ? true : false;
            $settings->test_mode_valsto = ($settings->test_mode_valsto === 'yes') ? true : false;
            $settings->http_proxy = ($settings->http_proxy === 'yes') ? true : false;
            
            if (empty($settings->merchant_account_valsto) || empty($settings->merchant_account_pk_valsto)) {
                throw new ErrorException("Plugin bad configured. Please set a valid <b>vMerchant Account</b> and <b>Public Key</b> in the Admin Settings");
            }
            
            $url = $this->getVar('vproxy');
            $postData['merchant'] = $settings->merchant_account_valsto;
            $postData['api_key'] = $settings->merchant_account_pk_valsto;
            $postData['currency_code'] = 'USD';
            $postData['allowed_domain'] = get_site_url();
            
            $postData = array_merge($_POST, $postData);
                                    
            $opts = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => http_build_query($postData)
                )
            );
            
            $context = stream_context_create($opts);
            
            $result = file_get_contents($url, false, $context);
            
            $cookies = array();
            foreach ($http_response_header as $hdr) {
                if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
                    parse_str($matches[1], $tmp);
                    $cookies += $tmp;
                }
            }
            
            foreach ($cookies as $key => $value) {
                setcookie($key, $value);
            }
            
            if ($settings->http_proxy) {
                preg_match('/action=".*"\s/', $result, $post_url);
                
                $result = str_replace($post_url, sprintf('action="%s"', WC_VALSTO_VBUTTON_INIT_URL), $result);
                if (isset($post_url[0])) {
                    $post_url = trim(str_replace('action=', '', $post_url[0]));
                    $result = str_replace('</form>', sprintf('<input type="hidden" value=%s name="url_proxy" /></form>', $post_url), $result);
                }
            }
            
            echo $result;
        } catch (ErrorException $e) {
            $this->handlerError($e);
        }
    }
    
    /**
     * Show the vPayment dialog content.
     */
    protected function initVPayment()
    {
        try {
            
            $cookieJID = "";
            
            if (isset($_COOKIE['JSESSIONID'])) {
                $cookieJID = sprintf("\r\nCookie: JSESSIONID=%s\r\n", $_COOKIE['JSESSIONID']);
            }
            
            $url = $this->getVar('url_proxy');
            
            if (strpos($url, "http://") !== false) {
                $url = str_replace("http://", "https://", $url);
            } else {
                if (strpos($url, "//") !== false) {
                    $url = str_replace("//", "https://", $url);
                }
            }
            
            $opts = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded\r\nContent-Language: en-US' . $cookieJID,
                    'content' => http_build_query($_POST)
                )
            );
            
            $context = stream_context_create($opts);
            
            session_write_close(); // unlock the file
            $result = file_get_contents($url, false, $context);
            session_start();
            
            $cookies = array();
            foreach ($http_response_header as $hdr) {
                if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
                    parse_str($matches[1], $tmp);
                    $cookies += $tmp;
                }
            }
            
            foreach ($cookies as $key => $value) {
                setcookie($key, $value);
            }
            
            preg_match('/action=".*"\s/', $result, $post_url);
            
            $result = str_replace($post_url, sprintf('action="%s"', WC_VALSTO_VBUTTON_INIT_URL), $result);
            
            if (isset($post_url[0])) {
                $post_url = trim(str_replace('action=', '', $post_url[0]));
                $result = str_replace('</form>', sprintf('<input type="hidden" value=%s name="url_proxy" /></form>', $post_url), $result);
            }
            
            echo $result;
        } catch (ErrorException $e) {
            $this->handlerError($e);
        }
    }
    
    /**
     * prepare the current purchase.
     */
    protected function preparePurchase()
    {
        try {
            if (! session_id()) {
                session_start();
            }
            
            $transactionId = $this->getVar('valsto_transaction_id', null);
            
            if ($transactionId !== null) {
                $_SESSION['valsto_transaction_id'] = $transactionId;
            } else {
                $_SESSION['current_order'] = $_POST;
            }
            
            header('HTTP/1.0 200 Success', true, 200);
        } catch (ErrorException $e) {
            $this->handlerError($e);
        }
    }
    
    /**
     * Webhook from Valsto Platform.
     */
    protected function webhook()
    {
        try {
            if (! session_id()) {
                session_start();
            }
                        
            $tId          = $this->getVar('vTID'); // Transaction ID
            $proccess     = $this->getVar('vTP'); // Proccess
            
            if ($tId === null || $proccess === null) {
                wp_redirect(get_home_url());
                return;
            }
            
            $settings = (object) get_option(WC_VALSTO_VBUTTON_SETTINGS, array());
            
            $url = sprintf(WC_VALSTO_VBUTTON_VALSTO_API_HOME . '/api/transactions/%s/%s/detail/%s', 
                $settings->merchant_account_valsto, 
                $settings->merchant_account_pk_valsto, // <- PUBLIC KEY
                $tId
            );
            
            $opts = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([
                        'privateApiKey' => $settings->merchant_account_private_pk_valsto
                    ])
                )
            );
            
            $context = stream_context_create($opts);
            
            if (isset($settings) && $settings->debug) {
                $this->log(sprintf("IPN URL: %s <br/>", $url));
            }
            
            $response = file_get_contents($url, null, $context);
            
            $transaction = json_decode($response);
            
            if ($transaction !== null) {
                
                switch ($proccess) {
                    case 'created':
                        if (isset($_SESSION['current_order'])) {
                            $status = [
                                'INITIATED',
                                'PUSH_NOTIFIED',
                                'ON_ADDRESS_VERIFICATION'
                            ];
                            if (in_array($transaction->currentStatus, $status)) {
                                $_GET['vDiscount'] = $transaction->vdiscount;
                                $_GET['vDiscountAmount'] = $transaction->vdiscountAmount;
                                $_GET['taxes'] = $transaction->taxes;
                                $order = get_order($tId);
                                if (! $order || $order == null) {
                                    $_POST = $_SESSION['current_order'];
                                    $woocommerce = WC();
                                    $checkout = $woocommerce->checkout();
                                    $checkout->process_checkout();
                                }
                            }
                        }
                        
                        break;
                        
                    case 'updated':
                        $status = [
                        'INITIATED' => 'wc-pending',
                        'PUSH_NOTIFIED' => 'wc-processing',
                        'PHONE_CHECKOUT' => 'wc-processing',
                        'COMPLETE_REJECTED' => 'wc-cancelled',
                        'COMPLETE_APPROVED' => 'wc-completed',
                        'INCOMPLETE_TIMEOUT' => 'wc-failed',
                        'COMPLETE_PROCESSED' => 'wc-completed',
                        'COMPLETE_CANCELED' => 'wc-cancelled',
                        'COMPLETE_RETURNED' => 'wc-refunded',
                        'ON_ADDRESS_VERIFICATION' => 'wc-processing'
                            ];
                        
                        if (isset($status[$transaction->currentStatus])) {
                            $newStatus = $status[$transaction->currentStatus];
                            $order = get_order($tId);
                            
                            if ($order && $order->post_status != $newStatus) {
                                $order->update_status($newStatus, sprintf('Valsto current status: %s', $transaction->currentStatusAlias));
                            }
                        }
                        break;
                        
                    default:
                        break;
                }
                header('HTTP/1.0 200 Success', true, 200);
            }
        } catch (ErrorException $e) {
            $this->handlerError($e);
        }
    }
    
    protected function throwErrorException($errno, $errstr, $errfile, $errline, array $errcontext){
        restore_error_handler();
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    
    /**
     * Handler and show the error.
     */
    protected function handlerError($e)
    {
        include ('templates/error.html');
        echo "<b>Error:</b>" . $e->getMessage();
        if (isset($settings) && $settings->debug) {
            echo "<b>Error:</b>" . $e->getMessage();
        }
    }
    
    /**
     * If it is not a POST request redirect to 404
     */
    protected function isPostRequestOrFail()
    {
        if (! $this->isPostRequest()) {
            if ($settings->debug) {
                $this->log( $_SERVER['REQUEST_METHOD'] );
            }
            wp_redirect(get_home_url());
            exit();
        }
    }
    
    /**
     * Return if the request by POST method. 
     * @return boolean
     */
    protected function isPostRequest()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     *
     * @param string $param
     * @param mixed $null_return
     * @return string|string[]
     */
    protected function getVar($param = null,$null_return = null)
    {
        if ($param){
            $value = (!empty($_POST[$param]) ? trim(esc_sql($_POST[$param])) : (!empty($_GET[$param]) ? trim(esc_sql($_GET[$param])) : $null_return ));
            return $value;
        } else {
            $params = array();
            foreach ($_POST as $key => $param) {
                $params[trim(esc_sql($key))] = (!empty($_POST[$key]) ? trim(esc_sql($_POST[$key])) :  $null_return );
            }
            foreach ($_GET as $key => $param) {
                $key = trim(esc_sql($key));
                if (!isset($params[$key])) { // if there is no key or it's a null value
                    $params[trim(esc_sql($key))] = (!empty($_GET[$key]) ? trim(esc_sql($_GET[$key])) : $null_return );
                }
            }
            return $params;
        }
    }
    
    /**
     * Allow this class and other classes to add slug keyed notices (to avoid duplication)
     */
    public function add_admin_notice( $slug, $class, $message )
    {
        $this->notices[ $slug ] = array(
            'class' => $class,
            'message' => $message
        );
    }
    
    /**
     * The primary sanity check, automatically disable the plugin on activation if it doesn't
     * meet minimum requirements.
     *
     * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
     */
    public static function activation_check() 
    {
        $environment_warning = self::get_environment_warning( true );
        if ( $environment_warning ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( $environment_warning );
        }
    }
    
    /**
     * The backup sanity check, in case the plugin is activated in a weird way,
     * or the environment changes after activation.
     */
    public function check_environment() 
    {
        $environment_warning = self::get_environment_warning();
        
        if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            $this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
        
        $settings = (object) get_option(WC_VALSTO_VBUTTON_SETTINGS,array());
        if ( (empty( $settings->merchant_account_pk_valsto ) || empty( $settings->merchant_account_private_pk_valsto )) && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
            $setting_link = $this->get_setting_link();
            
            $this->add_admin_notice( 'prompt_connect', 'notice notice-warning', __( 'The WooCommerce Valsto VPayment is almost ready. To get started, <a href="' . $setting_link . '">configure your Valsto Merchant account</a>.', self::PLUGIN_NAME_SPACE) );
        }
    }
    
    /**
     * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
     * found or false if the environment has no problems.
     */
    static function get_environment_warning( $during_activation = false )
    {
        
        if ( version_compare( phpversion(), WC_VALSTO_VBUTTON_MIN_PHP_VER, '<' ) ) {
            if ( $during_activation ) {
                $message = __( 'The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::PLUGIN_NAME_SPACE, self::PLUGIN_NAME_SPACE );
            } else {
                $message = __( 'The WooCommerce Valsto VPayment plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::PLUGIN_NAME_SPACE );
            }
            return sprintf( $message, WC_VALSTO_VBUTTON_MIN_PHP_VER, phpversion() );
        }
        
        if ( ! function_exists( 'curl_init' ) ) {
            if ( $during_activation ) {
                return __( 'The plugin could not be activated. cURL is not installed.', self::PLUGIN_NAME_SPACE );
            }
            
            return __( 'The WooCommerce Valsto VPayment plugin has been deactivated. cURL is not installed.', self::PLUGIN_NAME_SPACE);
        }
        
        return false;
    }
    
    
    /**
     * Adds plugin action links
     *
     * @since 1.0.0
     */
    public function plugin_action_links( $links )
    {
        $setting_link = $this->get_setting_link();
        
        $plugin_links = array(
            '<a href="' . $setting_link . '">' . __( 'Settings', self::PLUGIN_NAME_SPACE ) . '</a>',
            '<a href="http://docs.woothemes.com/document/valsto-payment/">' . __( 'Docs', self::PLUGIN_NAME_SPACE ) . '</a>',
            '<a href="http://support.woothemes.com/">' . __( 'Support', self::PLUGIN_NAME_SPACE ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }
    
    /**
     * Get setting link.
     *
     * @return string Valsto checkout setting link
     */
    public function get_setting_link()
    {
        return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=valsto_payment');
    }
    
    /**
     * Display any notices we've collected thus far (e.g. for connection, disconnection)
     */
    public function admin_notices()
    {
        foreach ( (array) $this->notices as $notice_key => $notice ) {
            echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
            echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
            echo "</p></div>";
        }
    }
    
    /**
     * Initialize the gateway. Called very early - in the context of the plugins_loaded action
     *
     * @since 1.0.0
     */
    public function init_gateways()
    {
        require_once( plugin_basename( 'classes/wc_gateway_valsto.php' ) );
        require_once( plugin_basename( 'classes/wc_gatefay_valsto_payment_button.php' ) );
        
        load_plugin_textdomain( self::PLUGIN_NAME_SPACE , false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
    }
    
    
    /**
     * Add the gateways to WooCommerce
     *
     * @since 1.0.0
     */
    public function add_gateways( $methods )
    {
        add_action('wp_enqueue_scripts', array( $this, 'gateways_enqueue_script' ));
        
        $methods[] = 'WC_Gateway_Valsto_Payment_Button';
        
        return $methods;
    }
    
    /**
     *
     */
    public function gateways_enqueue_script(){
        wp_enqueue_script ('valsto-payment-button-js', plugins_url( 'assets/js/vpayment-button.js', __FILE__ ));
        wp_enqueue_style  ('valsto-payment-button-css',plugins_url( 'assets/css/vpayment-button.css', __FILE__ ));
    }
    
    /**
     * Returns true if our gateways are enabled, false otherwise
     *
     * @since 1.0.0
     */
    public function are_our_gateways_enabled()
    {
        $gateway_settings = get_option( WC_VALSTO_VBUTTON_SETTINGS, array() );
        
        if ( empty( $gateway_settings ) ) {
            return false;
        }
        
        return ( "yes" === $gateway_settings['enabled'] );
    }
    
    
    /**
     * When cart based Checkout with Valsto is in effect, disable other gateways on checkout
     *
     * @since 1.0.0
     * @param array $gateways
     * @return array
     */
    public function possibly_disable_other_gateways( $gateways )
    {
        
        if ( WC_Valsto_VButton_Loader::getInstance()->does_session_have_postback_data() ) {
            foreach ( $gateways as $id => $gateway ) {
                if ( $id !== 'valsto_payment' ) {
                    unset( $gateways[ $id ] );
                }
            }
        }
        
        return $gateways;
    }
    
    /**
     * Check if postback data is present
     *
     * @since 1.0.0
     * @return bool
     */
    public function does_session_have_postback_data()
    {
        return isset( WC()->session->valsto_payment );
    }
    
    /**
     * Returns form fields common to all the gateways this extension supports
     *
     * @since 1.0.0
     */
    public function get_shared_form_fields ()
    {
        
        return array(
            'enabled' => array(
                'title'       => __( 'Enable Valsto Payment', self::PLUGIN_NAME_SPACE ),
                'label'       => '',
                'type'        => 'checkbox',
                'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', self::PLUGIN_NAME_SPACE ),
                'default'     => 'false',
                'desc_tip'    => true
            ),
            'title_valsto'    => array(
                'title'       => __( 'Valsto Payment Title', self::PLUGIN_NAME_SPACE ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout for Valsto.',self::PLUGIN_NAME_SPACE ),
                'default'     => 'Valsto Payment',
                'desc_tip'    => true
            ),
            'description_valsto' => array(
                'title'          => __( 'Valsto Payment Description', self::PLUGIN_NAME_SPACE ),
                'type'           => 'text',
                'description'    => __( 'This controls the description which the user sees during checkout for Valsto.', self::PLUGIN_NAME_SPACE ),
                'default'        => 'The secure way to pay less.',
                'desc_tip'       => true
            ),
            'merchant_account_valsto' => array(
                'title'               => __( 'Merchant Account Username', self::PLUGIN_NAME_SPACE ),
                'type'                => 'email',
                'description'         => __( 'This is the business username (email) used to log in to the Valsto merchant master account.', self::PLUGIN_NAME_SPACE ),
                'default'             => '',
                'desc_tip'            => true
            ),
            'merchant_account_pk_valsto' => array(
                'title'              => __( 'Merchant Account Public Key', self::PLUGIN_NAME_SPACE ),
                'type'               => 'password',
                'description'        => __( 'This controls the Merchant Account Public Key checkout for Valsto.', self::PLUGIN_NAME_SPACE ),
                'default'            => '',
                'desc_tip'           => true
            ),
            'merchant_account_private_pk_valsto' => array(
                'title'                      => __( 'Merchant Account Private Key', self::PLUGIN_NAME_SPACE ),
                'type'                       => 'password',
                'description'                => __( 'This controls the Merchant Account Private Key checkout for Valsto.', self::PLUGIN_NAME_SPACE ),
                'default'                    => '',
                'desc_tip'                   => true
            ),
            'debug' => array(
                'title'       => __( 'Debug', self::PLUGIN_NAME_SPACE ),
                'label'       => __( 'Enable debugging messages', self::PLUGIN_NAME_SPACE ),
                'type'        => 'checkbox',
                'description' => __( 'Sends debug messages to the WooCommerce System Status log.', self::PLUGIN_NAME_SPACE ),
                'default'     => 'yes'
            ),
            'test_mode_valsto' => array(
                'title'        => __( 'Test mode (sandbox)', self::PLUGIN_NAME_SPACE ),
                'label'        => __( 'Enable Sandbox', self::PLUGIN_NAME_SPACE ),
                'type'         => 'checkbox',
                'description'  => __( 'Enable test mode payments.', self::PLUGIN_NAME_SPACE ),
                'default'      => 'yes'
            ),
            'js_resource_valsto' => array(
                'title'          => __( 'Valsto Payment javascript resource', self::PLUGIN_NAME_SPACE ),
                'type'           => 'url',
                'description'    => __( 'Default value: ', self::PLUGIN_NAME_SPACE ) . $this->get_default_js_resource(),
                'default'        => $this->get_default_js_resource(),
            ),
            'http_proxy'       => array(
                'title'       => __( 'HTTP Proxy', self::PLUGIN_NAME_SPACE ),
                'label'       => __( 'Enable HTTP Proxy.', self::PLUGIN_NAME_SPACE ),
                'type'        => 'checkbox',
                'default'     => 'yes'
            ),
        );
        
    }
    
    /**
     * Returns the default js resource
     */
    public function get_default_js_resource()
    {
        return plugin_dir_url(__FILE__) . 'assets/vpayment-button.js';
    }
    
    /**
     *
     * @since 1.0.0
     */
    public function log($message)
    {
        if ( true === WP_DEBUG ) {
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( print_r( $message, true ) );
            } else {
                error_log( $message );
            }
        }
    }
    
    /**
     * Return the plugin's about URL.
     * @return string
     */
    public static function get_about_url()
    {
        return "https://www.valsto.com/";
    }
    
    /**
     * Return the login URL into Valsto Platform.
     * @return string
     */
    public static function get_login_url()
    {
        return "https://www.valsto.com/login";
    }
}

$GLOBALS['wc_valsto_vbutton_loader'] = WC_Valsto_VButton_Loader::getInstance();
register_activation_hook( __FILE__, array( 'WC_Valsto_VButton_Loader', 'activation_check' ));
