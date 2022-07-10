<?php
/**
 * Plugin Name: Inesonic History
 * Plugin URI: http://www.inesonic.com
 * Description: A small proprietary plug-in that provides history tracking for customer activities.
 * Version: 1.0.0
 * Author: Inesonic, LLC
 * Author URI: http://www.inesonic.com
 */

/***********************************************************************************************************************
 * Copyright 2020 - 2022, Inesonic, LLC.
 *
 * GNU Public License, Version 3:
 *   This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 *   License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any
 *   later version.
 *   
 *   This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 *   details.
 *   
 *   You should have received a copy of the GNU General Public License along with this program.  If not, see
 *   <https://www.gnu.org/licenses/>.
 ***********************************************************************************************************************
 */

require_once dirname(__FILE__) . '/include/rewrite-base.php';

/**
 * Inesonic WordPress plug-in class that tracks customer history.
 */
class InesonicHistory extends \Inesonic\History\RewriteBase {
    const VERSION = '1.0.0';
    const SLUG    = 'inesonic-history';
    const NAME    = 'Inesonic History Tracker';
    const AUTHOR  = 'Inesonic, LLC';
    const PREFIX  = 'InesonicHistory';

    /**
     * The slug to use for the customer history.
     */
    const CUSTOMER_HISTORY_SLUG = 'inesonic-customer-history';

    /**
     * The slug to return to the users page.
     */
    const USERS_PAGE_SLUG = '/wp-admin/users.php';

    /**
     * The singleton class instance.
     */
    private static $instance;  /* Plug-in instance */

    /**
     * The plug-in directory.
     */
    public static  $dir = '';  /* Plug-in directory */

    /**
     * The plug-in URL.
     */
    public static  $url = '';  /* Plug-in URL */

    /**
     * Our Twig template loader.
     */
    public static  $loader = null; /* The template loader */

    /**
     * Our Twig template environment.
     */
    public static  $template_environment = null; /* The template environment */

    /**
     * Method that is called to initialize a single instance of the plug-in
     */
    public static function instance() {
        if (!isset(self::$instance)                       &&
            !(self::$instance instanceof InesonicHistory)    ) {
            self::$instance = new InesonicHistory();
            self::$dir      = plugin_dir_path(__FILE__);
            self::$url      = plugin_dir_url(__FILE__);

            spl_autoload_register(array(self::$instance, 'autoloader'));
        }
    }

    /**
     * Static method that is triggered when the plug-in is activated.
     */
    public static function plugin_activated() {
        self::rewrite_plugin_activated(self::CUSTOMER_HISTORY_SLUG);

        if (defined('ABSPATH') && current_user_can('activate_plugins')) {
            $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
            if (check_admin_referer('activate-plugin_' . $plugin)) {
                global $wpdb;
                $wpdb->query(
                    'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'inesonic_history' . ' (' .
                        'event_id BIGINT UNSIGNED AUTO_INCREMENT,' .
                        'user_id BIGINT UNSIGNED NOT NULL,' .
                        'event_timestamp BIGINT UNSIGNED NOT NULL,' .
                        'event_type VARCHAR(40) NOT NULL,' .
                        'additional TEXT NOT NULL,' .
                        'PRIMARY KEY (event_id)' .
                    ')'
                );
            }
        }
    }

    /**
     * Static method that is triggered when the plug-in is deactivated.
     */
    public static function plugin_deactivated() {
        self::rewrite_plugin_deactivated(self::CUSTOMER_HISTORY_SLUG);
    }

    /**
     * Static method that is triggered when the plug-in is uninstalled.
     */
    public static function plugin_uninstalled() {
        if (defined('ABSPATH') && current_user_can('activate_plugins')) {
            $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
            if (check_admin_referer('deactivate-plugin_' . $plugin)) {
                global $wpdb;
                $wpdb->query('DROP TABLE ' . $wpdb->prefix . 'inesonic_history');
            }
        }

        self::rewrite_plugin_uninstalled(self::CUSTOMER_HISTORY_SLUG);
    }

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(self::CUSTOMER_HISTORY_SLUG);

        add_action('init', array($this, 'customize_on_initialization'));

        add_action('delete_user', array($this, 'about_to_delete_user'), 10, 3);
        add_filter('user_row_actions', array($this, 'add_user_row_actions'), 100, 2);

        /* Action: inesonic_add_history
         *
         * Adds a history record.
         *
         * Parameters:
         *
         *     $user_id -    The ID of the user this history record should be tied to.
         *
         *     $event_type - A string holding the event type.
         *
         *     $additional - Additional textual information tied to the event.
         */
        add_action('inesonic_add_history', array($this, 'add_history_record'), 10, 3);
    }

    /**
     * PHP PSR-4 autoloader.
     *
     * \param[in] $class_name The class to be auto-loaded.
     */
    public function autoloader($class_name) {
        if (!class_exists($class_name) and (FALSE !== strpos($class_name, self::PREFIX))) {
            $class_name = str_replace(self::PREFIX, '', $class_name);
            $classes_dir = realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
            $class_file = str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';

            if (file_exists($classes_dir . $class_file)) {
                require_once $classes_dir . $class_file;
            }
        }
    }


    /**
     * Method that performs additional initialization on WordPress initialization.
     */
    function customize_on_initialization() {
    }

    /**
     * Method that is triggered just before we delete a user from the database.
     *
     * \param[in] $user_id       The user ID of the user that is being deleted.
     *
     * \param[in] $reassigned_to The user ID of the user taking over this user.  A value of null indicates no
     *                           reassignemnt.
     *
     * \param[in] $user_data     The WP_User object for the user being deleted.
     */
    public function about_to_delete_user($user_id, $reassigned_to, $user_data) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'inesonic_history', array('user_id' => $user_id), array('%d'));

        $first_name = get_user_meta($user_data->ID, 'first_name', true);
        $last_name = get_user_meta($user_data->ID, 'last_name', true);
        $company = get_user_meta($user_data->ID, 'company', true);
        $email_address = $user_data->user_email;

        $additional = $first_name . ' ' . $last_name . ' / ' . $company . ' - ' . $email_address .
                      '(' . implode(",", $user_data->roles) . ')';

        $this->add_history_record($user_id, 'USER_DELETED', $additional);
    }

    /**
     * Function that is triggered to add additional actions to the user's menu, per user.   We also remove actions that
     * do not make sense for this site.
     *
     * \param[in] $actions     An array of actions to be updated.
     *
     * \param[in] $user_object The user object for the specific user.
     *
     * \return Returns the modified user actions.
     */
    public static function add_user_row_actions($actions, $user_object) {
        $current_user = wp_get_current_user();
        if (!is_multisite() && current_user_can('edit_user', $user_object->ID )) {
            $actions['history'] = '<a class="history" href="' .
                                     site_url(self::CUSTOMER_HISTORY_SLUG . '/?customer_id=' . $user_object->ID) .
                                  '">' .
                                    __("History", 'inesonic-history') .
                                  '</a>';
        }

        unset($actions['resetpassword']);
        unset($actions['view']);

        return $actions;
    }

    /**
     * Method that is triggered when a new history record is to be added.
     *
     * \param[in] $user_id    The ID of the user.
     *
     * \param[in] $event_type A free-from string indicating the event type.
     *
     * \param[in] $additional Additional text to include with the history record.
     */
    public function add_history_record($user_id, $event_type, $additional) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'inesonic_history',
            array(
                'user_id' => $user_id,
                'event_timestamp' => time(),
                'event_type' => $event_type,
                'additional' => $additional
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s'
            )
        );
    }

    /**
     * Method that handles the redirect.  Overload this method in derived classes.
     */
    public function process_redirect() {
        if (current_user_can('edit_users')) {
            global $wpdb;

            $query_string = 'SELECT * FROM ' . $wpdb->prefix . 'inesonic_history';
            $first_condition = true;

            if (array_key_exists('customer_id', $_REQUEST)) {
                $customer_id = intval(sanitize_key($_REQUEST['customer_id']));
                $query_string .= ' WHERE user_id=' . $customer_id;
                $first_condition = false;
            } else {
                $customer_id = null;
            }

            if (array_key_exists('start_timestamp', $_REQUEST)) {
                $start_timestamp = intval(sanitize_key($_REQUEST['start_timestamp']));
                if ($first_condition) {
                    $query_string .= ' WHERE event_timestamp>=' . $start_timestamp;
                    $first_condition = false;
                } else {
                    $query_string .= ' AND event_timestamp>=' . $start_timestamp;
                }
            }

            if (array_key_exists('end_timestamp', $_REQUEST)) {
                $end_timestamp = intval(sanitize_key($_REQUEST['end_timestamp']));
                if ($first_condition) {
                    $query_string .= ' WHERE event_timestamp<=' . $end_timestamp;
                    $first_condition = false;
                } else {
                    $query_string .= ' AND event_timestamp<=' . $end_timestamp;
                }
            } else {
                $end_timestamp = null;
            }

            $query_string .= ' ORDER BY event_timestamp ASC';
            $query_results = $wpdb->get_results($query_string);

            echo __(
                '<style>
                   table {
                     border-collapse: collapse;
                     border: solid 1px black;
                   }
                   thead {
                     font-weight: bold;
                   }
                   td {
                     padding: 5px;
                     border-collapse: collapse;
                     border: solid 1px gray;
                   }
                   .nowrap {
                     white-space: nowrap;
                   }
                 </style>',
                'inesonic-history'
            );

            if ($customer_id === null || $customer_id == 0) {
                echo "<h1>" . __("All Events:") . "</h1>";
            } else {
                echo "<h1>" . sprintf(__("Events For Customer %d:", 'inesonic-history'), $customer_id) . "</h1>";
            }

            echo '<table>
                    <thead>
                      <tr>
                        <td>#</td>
                        <td class="nowrap">' . __("Customer&nbsp;ID", 'inesonic-history') . '</td>
                        <td class="nowrap">' . __("Event&nbsp;Date/Time", 'inesonic-history') . '</td>
                        <td class="nowrap">' . __("Event&nbsp;Type", 'inesonic-history') . '</td>
                        <td class="nowrap">' . __("Additional", 'inesonic-history') . '</td>
                      </tr>
                    </thead>
                    <tbody>';

            foreach($query_results as $event) {
                echo '<tr>
                        <td>' . esc_html($event->event_id) . '</td>
                        <td>' . esc_html($event->user_id) . '</td>
                        <td>' . esc_html(date('Y-m-d H:i:s', $event->event_timestamp)) . '</td>
                        <td>' . esc_html($event->event_type) . '</td>
                        <td>' . esc_html($event->additional) . '</td>
                      </tr>';
            }

            echo '  </tbody>" .
                  </table>
                  <p align="center">';

            if ($customer_id !== null && $customer_id != 0) {
                echo '<a href="' . site_url($this->rewrite_slug()) . '">' .
                       __("Show History For All Users", 'inesonic-history') .
                     '</a>
                     &nbsp;&nbsp;&nbsp;&nbsp;';
            }

            echo   '<a href="' . site_url(self::USERS_PAGE_SLUG) . '">' .
                     __("Returns To Administrative Page", 'inesonic-history') .
                   '</a>
                  </p>';

            die();
        } else {
            // By exiting this function, the function will behave as if the slug is not valid.
        }
    }
}

/* Instatiate our plug-in. */
InesonicHistory::instance();

/* Define critical global hooks. */
register_activation_hook(__FILE__, array('InesonicHistory', 'plugin_activated'));
register_deactivation_hook(__FILE__, array('InesonicHistory', 'plugin_deactivated'));
register_uninstall_hook(__FILE__, array('InesonicHistory', 'plugin_uninstalled'));
