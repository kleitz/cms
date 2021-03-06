<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since    2.0.0
 * @author   Christopher Castro <chris@quickapps.es>
 * @link     http://www.quickappscms.org
 * @license  http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
namespace QuickApps\Core;

use Cake\Core\App;
use Cake\Core\Plugin as CakePlugin;
use Cake\Error\FatalErrorException;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use QuickApps\Core\Package\PackageFactory;
use QuickApps\Core\Package\PluginPackage;
use QuickApps\Core\StaticCacheTrait;

/**
 * Plugin is used to load and locate plugins.
 *
 * Wrapper for `Cake\Core\Plugin`, it adds some QuickAppsCMS specifics methods.
 */
class Plugin extends CakePlugin
{

    use StaticCacheTrait;

    /**
     * Get the given plugin as an object, or a collection of objects if not
     * specified.
     *
     * @param string $plugin Plugin name to get, or null to get a collection of
     *  all plugin objects
     * @return \QuickApps\Core\Package\PluginPackage|\Cake\Collection\Collection
     */
    public static function get($plugin = null)
    {
        $cacheKey = "get({$plugin})";
        $cache = static::cache($cacheKey);

        if ($cache !== null) {
            return $cache;
        }

        if ($plugin === null) {
            $collection = [];
            foreach ((array)quickapps('plugins') as $plugin) {
                $plugin = PackageFactory::create($plugin['name']);
                if ($plugin instanceof PluginPackage) {
                    $collection[] = $plugin;
                }
            }
            return static::cache($cacheKey, collection($collection));
        }

        $package = PackageFactory::create($plugin);
        if ($package instanceof PluginPackage) {
            return static::cache($cacheKey, $package);
        }

        throw new FatalErrorException(__('Plugin "{0}" was not found', $plugin));
    }

    /**
     * Scan plugin directories and returns plugin names and their paths within file
     * system. We consider "plugin name" as the name of the container directory.
     *
     * Example output:
     *
     * ```php
     * [
     *     'Users' => '/full/path/plugins/Users',
     *     'ThemeManager' => '/full/path/plugins/ThemeManager',
     *     ...
     *     'MySuperPlugin' => '/full/path/plugins/MySuperPlugin',
     *     'DarkGreenTheme' => '/full/path/plugins/DarkGreenTheme',
     * ]
     * ```
     *
     * If $ignoreThemes is set to true `DarkGreenTheme` will not be part of the result
     *
     * @param bool $ignoreThemes Whether include themes as well or not
     * @return array Associative array as `PluginName` => `full/path/to/PluginName`
     */
    public static function scan($ignoreThemes = false)
    {
        $cacheKey = "scan({$ignoreThemes})";
        $cache = static::cache($cacheKey);
        if (!$cache) {
            $cache = [];
            $paths = App::path('Plugin');
            $Folder = new Folder();
            $Folder->sort = true;
            foreach ($paths as $path) {
                $Folder->cd($path);
                foreach ($Folder->read(true, true, true)[0] as $dir) {
                    $name = basename($dir);
                    if ($ignoreThemes && str_ends_with($name, 'Theme')) {
                        continue;
                    }
                    $cache[$name] = normalizePath($dir);
                }
            }
        }
        return $cache;
    }

    /**
     * Checks whether a plugins ins installed on the system regardless of its status.
     *
     * @param string $plugin Plugin to check
     * @return bool True if exists, false otherwise
     */
    public static function exists($plugin)
    {
        $check = quickapps("plugins.{$plugin}");
        return !empty($check);
    }

    /**
     * Validates a composer.json file.
     *
     * Below a list of validation rules that are applied:
     *
     * - must be a valid JSON file.
     * - key `name` must be present and follow the pattern `author/package`
     * - key `type` must be present and be "quickapps-plugin" or "cakephp-plugin" (even if it's a theme).
     * - key `extra.regions` must be present if it's a theme (its name ends with
     *   `-theme`, e.g. `quickapps/blue-sky-theme`)
     *
     * ### Usage:
     *
     * ```php
     * $json = json_decode(file_gets_content('/path/to/composer.json'), true);
     * Plugin::validateJson($json);
     *
     * // OR:
     *
     * Plugin::validateJson('/path/to/composer.json');
     * ```
     *
     * @param array|string $json JSON given as an array result of
     *  `json_decode(..., true)`, or a string as path to where .json file can be found
     * @param bool $errorMessages If set to true an array of error messages
     *  will be returned, if set to false boolean result will be returned; true on
     *  success, false on validation failure failure. Defaults to false (boolean result)
     * @return array|bool
     */
    public static function validateJson($json, $errorMessages = false)
    {
        if (is_string($json) && file_exists($json) && !is_dir($json)) {
            $json = json_decode((new File($json))->read(), true);
        }

        $errors = [];
        if (!is_array($json) || empty($json)) {
            $errors[] = __('Corrupt JSON information.');
        } else {
            if (!isset($json['type'])) {
                $errors[] = __('Missing field: "{0}"', 'type');
            } elseif (!in_array($json['type'], ['quickapps-plugin', 'cakephp-plugin'])) {
                $errors[] = __('Invalid field: "{0}" ({1}). It should be: {2}', 'type', $json['type'], 'quickapps-plugin or cakephp-plugin');
            }

            if (!isset($json['name'])) {
                $errors[] = __('Missing field: "{0}"', 'name');
            } elseif (!preg_match('/^(.+)\/(.+)+$/', $json['name'])) {
                $errors[] = __('Invalid field: "{0}" ({1}). It should be: {2}', 'name', $json['name'], '{author-name}/{package-name}');
            } elseif (str_ends_with(strtolower($json['name']), 'theme')) {
                if (!isset($json['extra']['regions'])) {
                    $errors[] = __('Missing field: "{0}"', 'extra.regions');
                }
            }
        }

        if ($errorMessages) {
            return $errors;
        }

        return empty($errors);
    }

    /**
     * Checks if there is any active plugin that depends of $pluginName.
     *
     * @param string $pluginName Plugin name to check
     * @return array A list of all plugin names that depends on $pluginName, an
     *  empty array means that no other plugins depends on $pluginName, so
     *  $pluginName can be safely deleted or turned off.
     */
    public static function checkReverseDependency($pluginName)
    {
        $out = [];
        list(, $pluginName) = packageSplit($pluginName, true);
        $plugins = static::get()
            ->filter(function ($plugin) use ($pluginName) {
                return
                    $plugin->status &&
                    strtolower($plugin->name) !== strtolower($pluginName);
            });

        foreach ($plugins as $plugin) {
            $dependencies = $plugin->dependencies($plugin->name);
            if (!empty($dependencies)) {
                $packages = array_map(
                    function ($item) {
                        list(, $package) = packageSplit($item, true);
                        return strtolower($package);
                    },
                    array_keys($dependencies)
                );

                if (in_array(strtolower($pluginName), $packages)) {
                    $out[] = $plugin->human_name;
                }
            }
        }
        return $out;
    }
}
