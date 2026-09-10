<?php
/**
 * Client Cards plugin handler
 *
 * @package blesta
 * @subpackage blesta.plugins.client_cards
 * @copyright Copyright (c) 2020, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class ClientCardsPlugin extends Plugin
{
    /**
     * Initialize the plugin
     */
    public function __construct()
    {
        Language::loadLang('client_cards_plugin', null, dirname(__FILE__) . DS . 'language' . DS);

        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');
    }

    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version
     *
     * @param string $current_version The current installed version of this plugin
     * @param int $plugin_id The ID of the plugin being upgraded
     */
    public function upgrade($current_version, $plugin_id)
    {
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }

        // Upgrade to 1.3.0
        if (version_compare($current_version, '1.3.0', '<')) {
            $this->updateCardColors();
        }
    }

    /**
     * Updates the default card background colors to match the Meridian palette
     * (services -> primary blue, invoices -> warning amber). Only cards still using the
     * previous default colors are updated, preserving any admin customizations.
     */
    private function updateCardColors()
    {
        // Map the previous default backgrounds to the new Meridian palette
        $colors = ['#ffc107' => '#2563eb', '#28a746' => '#d97706'];

        // Gather this plugin's instances across all companies
        $plugins = $this->Record->select(['id'])
            ->from('plugins')
            ->where('dir', '=', 'client_cards')
            ->fetchAll();
        $plugin_ids = array_map(function ($plugin) {
            return $plugin->id;
        }, $plugins);

        if (empty($plugin_ids)) {
            return;
        }

        foreach ($colors as $from => $to) {
            $this->Record->where('plugin_id', 'in', $plugin_ids)
                ->where('background', '=', $from)
                ->update('plugin_cards', ['background' => $to]);
        }
    }

    /**
     * Retrieves the total number of active and suspended services
     *
     * @param int $client_id The ID of the client assigned to the services
     * @return int The total number of active and suspended services
     */
    public function getServicesCount($client_id)
    {
        Loader::loadModels($this, ['Services', 'PluginManager']);

        // Exclude domains, if the domain manager plugin is installed
        $filters = [];
        if ($this->PluginManager->isInstalled('domains', Configure::get('Blesta.company_id'))) {
            $filters['type'] = 'services';
        }

        return $this->Services->getListCount($client_id, 'active', false, null, $filters)
            + $this->Services->getListCount($client_id, 'suspended', false, null, $filters);
    }

    /**
     * Retrieves the total number of active invoices/proformas
     *
     * @param int $client_id The ID of the client assigned to the invoices/proformas
     * @return int The total number of active invoices/proformas
     */
    public function getInvoicesCount($client_id)
    {
        Loader::loadModels($this, ['Invoices']);

        return $this->Invoices->getListCount($client_id);
    }

    /**
     * Returns all cards to be configured for this plugin (invoked after install() or upgrade(),
     * overwrites all existing cards)
     *
     * @return array A numerically indexed array containing:
     *
     *  - level The level this card should be displayed on (client or staff) (optional, default client)
     *  - callback A method defined by the plugin class for calculating the value of the card or fetching a custom html
     *  - callback_type The callback type, 'value' to fetch the card value or
     *      'html' to fetch the custom html code (optional, default value)
     *  - background The background color in hexadecimal or path to the background image for this card (optional)
     *  - background_type The background type, 'color' to set a hexadecimal background or
     *      'image' to set an image background (optional, default color)
     *  - label A string or language key appearing under the value as a label
     *  - link The link to which the card will be pointed to (optional)
     *  - enabled Whether this card appears on client profiles by default
     *      (1 to enable, 0 to disable) (optional, default 1)
     */
    public function getCards()
    {
        return [
            [
                'level' => 'client',
                'callback' => ['this', 'getServicesCount'],
                'callback_type' => 'value',
                'label' => 'ClientCardsPlugin.card_client.services',
                'text_color' => '#ffffff',
                'background' => '#2563eb',
                'background_type' => 'color',
                'icon' => 'bi bi-hdd-stack',
                'link' => 'services/index/active/',
                'enabled' => 1
            ],
            [
                'level' => 'client',
                'callback' => ['this', 'getInvoicesCount'],
                'callback_type' => 'value',
                'label' => 'ClientCardsPlugin.card_client.invoices',
                'text_color' => '#ffffff',
                'background' => '#d97706',
                'background_type' => 'color',
                'icon' => 'bi bi-receipt',
                'link' => 'invoices/index/open/',
                'enabled' => 1
            ]
        ];
    }
}
