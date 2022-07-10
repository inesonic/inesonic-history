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
    require_once dirname(__FILE__) . '/rewrite-base.php';
    require_once dirname(__FILE__) . '/rest-api-v1.php';

    /**
     * Class that generates a dump of history about a given customer.
     */
    class CustomerHistory extends RewriteBase {
        /**
         * The slug to return to the users page.
         */
        const USERS_PAGE_SLUG = '/wp-admin/users.php';

        /**
         * Constructor
         *
         * \param $rewrite_slug     The slug to bind to.
         *
         * \param $rest_api         The REST API to use to request user data.
         *
         * \param $history_endpoint The endpoint/slug used to obtain user history.
         *
         * \param $secret           The secret required to request the customer data.
         */
        public function __construct(
                string    $rewrite_slug,
                RestApiV1 $rest_api,
                string    $history_endpoint,
                string    $secret
            ) {
            parent::__construct($rewrite_slug);
            $this->rest_api = $rest_api;
            $this->history_endpoint = $history_endpoint;
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

                if (array_key_exists('start_timestamp', $_REQUEST)) {
                    $start_timestamp = $_REQUEST['start_timestamp'];
                } else {
                    $start_timestamp = null;
                }

                if (array_key_exists('end_timestamp', $_REQUEST)) {
                    $end_timestamp = $_REQUEST['end_timestamp'];
                } else {
                    $end_timestamp = null;
                }

                if (array_key_exists('events', $_REQUEST)) {
                    $events = $_REQUEST['events'];
                } else {
                    $events = null;
                }

                $payload = array(
                    'customer_id' => $customer_id,
                    'start_timestamp' => $start_timestamp,
                    'end_timestamp' => $end_timestamp,
                    'events' => $events
                );

                $response = $this->rest_api->post_message(
                    $this->history_endpoint,
                    $this->secret,
                    $payload
                );

                if ($response !== null && $response['status'] == 'OK') {
                    $events = $response['events'];

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
                         "</style>";

                    if ($customer_id === null || $customer_id == 0) {
                        echo "<h1>" . __("All Events:") . "</h1>";
                    } else {
                        echo "<h1>" . sprintf(__("Events For Customer %d:"), $customer_id) . "</h1>";
                    }

                    echo "<table>" .
                           "<thead>" .
                             "<tr>" .
                               "<td>#</td>" .
                               "<td>" . __("Customer&nbsp;ID") . "</td>" .
                               "<td>" . __("Event&nbsp;Date/Time") . "</td>" .
                               "<td>" . __("Event&nbsp;Type") . "</td>" .
                               "<td>" . __("Additional") . "</td>" .
                             "</tr>" .
                           "</thead>" .
                           "<tbody>";

                    foreach($events as $event) {
                        echo "<tr>" .
                               "<td>" . esc_html($event['event_id']) . "</td>" .
                               "<td>" . esc_html($event['customer_id']) . "</td>" .
                               "<td>" . esc_html($event['event_datetime']) . "</td>" .
                               "<td>" . esc_html($event['event_type']) . "</td>" .
                               "<td>" . esc_html($event['additional']) . "</td>" .
                             "</tr>";
                    }

                    echo   "</tbody>" .
                         "</table>" .
                         "<p align=\"center\">";

                    if ($customer_id !== null && $customer_id != 0) {
                        echo "<a href=\"/" . $this->rewrite_slug() . "\">" .
                               __("Show History For All Users") .
                             "</a>" .
                             "&nbsp;&nbsp;&nbsp;&nbsp;";
                    }

                    echo   "<a href=\"/" . $this->rewrite_slug() . "/?events=user-purged\">" .
                               __("Show Purged Users") .
                           "</a>" .
                           "&nbsp;&nbsp;&nbsp;&nbsp;" .
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
