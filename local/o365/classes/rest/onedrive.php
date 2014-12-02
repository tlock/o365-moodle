<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package local_o365
 * @author James McQuillan <james.mcquillan@remote-learner.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2014 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 */

namespace local_o365\rest;

/**
 * API client for o365 onedrive.
 */
class onedrive extends \local_o365\rest\o365api {
    /**
     * Determine if the API client is configured.
     *
     * @return bool Whether the API client is configured.
     */
    public static function is_configured() {
        $config = get_config('local_o365');
        return (!empty($config->tenant)) ? true : false;
    }

    /**
     * Get the API client's oauth2 resource.
     *
     * @return string The resource for oauth2 tokens.
     */
    public static function get_resource() {
        $config = get_config('local_o365');
        if (!empty($config->tenant)) {
            return 'https://'.$config->tenant.'-my.sharepoint.com';
        } else {
            return false;
        }
    }

    /**
     * Get the base URI that API calls should be sent to.
     *
     * @return string|bool The URI to send API calls to, or false if a precondition failed.
     */
    public function get_apiuri() {
        return static::get_resource().'/_api/v1.0/me/Files';
    }

    /**
     * Get the contents of a folder.
     *
     * @param string $path The path to read.
     * @return array|null Returned response, or null if error.
     */
    public function get_contents($path) {
        $path = rawurlencode($path);
        $response = $this->apicall('get', "/getByPath('{$path}')/children");
        $response = json_decode($response, true);
        if (empty($response)) {
            throw new \Exception('Error in API call.');
        }
        return $response;
    }

    /**
     * Get a file by it's path.
     *
     * @param string $path The file's path.
     * @return string The file's content.
     */
    public function get_file_by_path($path) {
        $path = rawurlencode($path);
        return $this->apicall('get', "/getByPath('{$path}')/content");
    }

    /**
     * Get a file by it's file id.
     *
     * @param string $fileid The file's ID.
     * @return string The file's content.
     */
    public function get_file_by_id($fileid) {
        return $this->apicall('get', "/{$fileid}/content");
    }
}