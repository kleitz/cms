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
namespace QuickApps\Core\Package;

/**
 * Represents a PHP library.
 *
 */
class LibraryPackage extends BasePackage
{

    /**
     * {@inheritdoc}
     *
     * ### Example:
     *
     * ```php
     * // Unexisting library, returns: empty
     * $this->version('unexisting-extension');
     *
     * // PHP version, returns: PHP_VERSION
     * $this->version('php');
     *
     * // Some PHP extension
     * $this->version('ext-intl');
     * ```
     *
     * @return string Package's version, for instance `1.2.x-dev`
     */
    public function version()
    {
        if (parent::version() !== null) {
            return parent::version();
        }

        $lib = strtolower($this->_packageName);
        if ($lib === 'lib-icu') {
            $lib = 'intl';
        } elseif (stripos($lib, 'ext-') === 0) {
            $lib = substr($lib, 4);
        }

        if ($lib === 'php') {
            $this->_version = PHP_VERSION;
        } elseif (function_exists('phpversion')) {
            $version = phpversion($lib);
            $this->_version = $version === false ? '' : $version;
        } elseif (function_exists('extension_loaded')) {
            $this->_version = extension_loaded($lib) ? '99999' : '';
        }

        return $this->_version;
    }
}
