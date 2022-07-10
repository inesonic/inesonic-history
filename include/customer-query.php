<?php
/***********************************************************************************************************************
 * Copyright 2020 - 2022, Inesonic, LLC
 *
 * GNU Public License, Version 2:
 *   This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 *   License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any
 *   later version.
 *   
 *   This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 *   details.
 *   
 *   You should have received a copy of the GNU General Public License along with this program; if not, write to the
 *   Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************************************************************************
 */

namespace Inesonic\Customizations;
    require_once dirname(__FILE__) . '/../vendor/autoload.php';
    require_once dirname(__FILE__) . '/rewrite-base.php';
    require_once dirname(__FILE__) . '/rest-api-v1.php';

    use Symfony\Component\Yaml\Yaml;

    /**
     * Class that generates a dump of all information about a given customer.
     */
    class CustomerQuery extends RewriteBase {
        /**
         * The slug to return to the users page.
         */
        const USERS_PAGE_SLUG = '/wp-admin/users.php';

        /**
         * Constructor
         *
         * \param $rewrite_slug   The slug to bind to.
         *
         * \param $rest_api       The REST API to use to request user data.
         *
         * \param $query_endpoint The endpoint/slug used to obtain user query data.
         *
         * \param $secret         The secret required to request the customer data.
         */
        public function __construct(
                string    $rewrite_slug,
                RestApiV1 $rest_api,
                string    $query_endpoint,
                string    $secret
            ) {
            parent::__construct($rewrite_slug);
            $this->rest_api = $rest_api;
            $this->query_endpoint = $query_endpoint;
            $this->secret = $secret;
        }

        /**
         * Method that handles the redirect.  Overload this method in derived classes.
         */
        public function process_redirect() {
            if (current_user_can('edit_users')) {
                if (array_key_exists('customer_id', $_REQUEST)) {
                    $customer_id = $_REQUEST['customer_id'];
                } else {
                    $customer_id = null;
                }

                if (array_key_exists('show_sensitive', $_REQUEST)) {
                    $show_sensitive = intval($_REQUEST['show_sensitive']);
                } else {
                    $show_sensitive = 0;
                }

                $payload = array(
                    'customer_id' => $customer_id,
                    'show_sensitive' => $show_sensitive
                );

                $response = $this->rest_api->post_message(
                    $this->query_endpoint,
                    $this->secret,
                    $payload
                );

                if ($response !== null && $response['status'] == 'OK') {
                    $data = $response['data'];

                    echo "<style>" .
                           "table {" .
                             "border-collapse: collapse;" .
                             "border: solid 1px black;" .
                           "}" .
                           "thead {" .
                             "font-weight: bold;" .
                           "}" .
                           "td {" .
                             "padding: 5px;" .
                             "border-collapse: collapse;" .
                             "border: solid 1px gray;" .
                           "}" .
                         "</style>" .
                         "<h1>" . sprintf(__("Data For Customer %d:"), $customer_id) . "</h1>" .
                         "<table>" .
                           "<tr>" .
                             "<td>" .
                               "<pre>" .
                                 esc_html(Yaml::dump($data, 100, 4)) .
                               "</pre>" .
                             "</td>" .
                           "</tr>" .
                         "</table>" .
                         "<p align=\"center\">";

                    if ($show_sensitive) {
                        echo "<a href=\"/" .
                               $this->rewrite_slug() . "/?customer_id=" . $customer_id . "&show_sensitive=0" .
                             "\">" .
                               __("Hide Sensitive Data") .
                             "</a>";
                    } else {
                        echo "<a href=\"/" .
                               $this->rewrite_slug() . "/?customer_id=" . $customer_id . "&show_sensitive=1" .
                             "\">" .
                               __("Reveal Sensitive Data") .
                             "</a>";
                    }

                    echo   "&nbsp;&nbsp;&nbsp;&nbsp;" .
                           "<a href=\"" . self::USERS_PAGE_SLUG . "\">" .
                             __("Returns To Administrative Page") .
                           "</a>" .
                         "</p>";
                } else {
                    echo "<h1>Invalid response from backend.</h1>";
                }

                die();
            } else {
                // By exiting this function, the function will behave as if the slug is not valid.
            }
        }
    };
