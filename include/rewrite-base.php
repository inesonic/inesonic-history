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

namespace Inesonic\History;
    /**
     * Base class used to simplify use of rewrite rules.  Portions of this code were inspired by the example at:
     *
     *   https://wordpress.stackexchange.com/questions/86960/using-the-rewrite-api-to-construct-a-restful-url
     */
    class RewriteBase {
        /**
         * Static method that is triggered when the plug-in is activated.
         *
         * \param $rewrite_slug Slug we are adding as a rewrite rule.
         */
        public static function rewrite_plugin_activated(mixed $rewrite_slug) {
            add_rewrite_endpoint($rewrite_slug, EP_ROOT);
            flush_rewrite_rules();
        }

        /**
         * Static method that is triggered when the plug-in is deactivated.
         *
         * \param $rewrite_slug Slug that was added to the rewrite rules..
         */
        public static function rewrite_plugin_deactivated(mixed $rewrite_slug) {
            flush_rewrite_rules();
        }

        /**
         * Static method that is triggered when the plug-in is uninstalled.
         *
         * \param $local_redirect_slug Slug that was added to the rewrite rules.
         */
        public static function rewrite_plugin_uninstalled(mixed $rewrite_slug) {}

        /**
         * Constructor
         *
         * \param $local_redirect_slug Endpoint used by the REST API.
         */
        public function __construct(string $rewrite_slug) {
            $this->current_rewrite_slug = $rewrite_slug;

            add_action('init', array($this, 'on_initialization'), 1000); // We do this action really late.

            if (!is_admin()) {
                add_filter('request', array($this, 'set_query_variable'));
                add_action('template_redirect', array($this, 'check_redirect'), 100);
            }
        }

        /**
         * Method you can use to determine the rewrite slug setup by this rewrite rule.
         *
         * \return Returns the rewrite slug redirected by this rewrite rule.
         */
        public function rewrite_slug() {
            return $this->current_rewrite_slug;
        }

        /**
         * Method that is trigger on initialization to setup the endpoint.
         */
        public function on_initialization() {
            add_rewrite_endpoint($this->current_rewrite_slug, EP_ROOT);

            // According to the source listed at the top of this class definition, WordPress will sometimes throw out
            // flushed rewrite rules.  We check for that here and flush again only if needed.

            global $wp_rewrite;
            $number_endpoints = count($wp_rewrite->endpoints);
            $index = 0;
            while ($index < $number_endpoints && $wp_rewrite->endpoints[$index][1] != $this->current_rewrite_slug) {
                ++$index;
            }

            if ($index >= $number_endpoints) {
                flush_rewrite_rules(false);
            }
        }

        /**
         * Method that checks if we've hit the endpoint and sets the endpoint variable.
         *
         * \param $query_variables The array of query variables.
         *
         * \return Returns the updated array of query variables.
         */
        public function set_query_variable(Array $query_variables) {
            if (empty($query_variables[$this->current_rewrite_slug])) {
                // The two checks below represents that prevents WordPress from seeing the redirect request as a normal
                // page request.  Again, based on information noted in the example referenced above.

                if (isset($query_variables['pagename'])                         &&
                    $this->current_rewrite_slug == $query_variables['pagename']    ) {
                    $query_variables['pagename'] == false;
                }

                if (isset($query_variables['page']) && $this->current_rewrite_slug == $query_variables['page']) {
                    $query_variables['page'] == false;
                }

                if (isset($query_variables[$this->current_rewrite_slug])) {
                    $query_variables[$this->current_rewrite_slug] = true;
                }
            }

            return $query_variables;
        }

        /**
         * Method that checks if we've hit this redirect.
         */
        public function check_redirect() {
            if (get_query_var($this->current_rewrite_slug) === true) {
                $this->process_redirect();
            }
        }

        /**
         * Method that handles the redirect.  Overload this method in derived classes.
         */
        public function process_redirect() {
            http_response_code(404);
            die();
        }
    };
