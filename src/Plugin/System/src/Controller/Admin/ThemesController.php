<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since	 1.0.0
 * @author	 Christopher Castro <chris@quickapps.es>
 * @link	 http://www.quickappscms.org
 * @license	 http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
namespace System\Controller\Admin;

use Cake\Error\NotFoundException;
use System\Controller\AppController;
use QuickApps\Core\Plugin;

/**
 * Controller for handling plugin tasks.
 *
 * Here is where can install new plugin or remove existing ones.
 */
class ThemesController extends AppController {

/**
 * Main action.
 *
 * @return void
 */
	public function index() {
		$themes = Plugin::collection(true)
			->match(['isTheme' => true])
			->toArray();
		$this->set('themes', $themes);
		$this->Breadcrumb->push('/admin/system/themes');
	}

/**
 * Handles theme's specifics settings.
 *
 * @return void
 */
	public function settings($themeName) {
		$theme = Plugin::info($themeName, true);
		$arrayContext = [
			'schema' => [],
			'defaults' => [],
			'errors' => [],
		];

		if (!$theme['hasSettings'] || !$theme['isTheme']) {
			throw new NotFoundException(__d('system', 'The requested page was not found.'));
		}

		if (!empty($this->request->data)) {
			$this->loadModel('System.Plugins');
			$themeEntity = $this->Plugins->get($themeName);
			$themeEntity->set('settings', $this->request->data);

			if ($this->Plugins->save($themeEntity)) {
				$this->alert(__d('system', 'Plugin settings saved!'), 'success');
				$this->redirect($this->referer());
			} else {
				$this->alert(__d('system', 'Plugin settings could not be saved'), 'danger');
				$errors = $themeEntity->errors();

				if (!empty($errors)) {
					foreach ($errors as $field => $message) {
						$arrayContext['errors'][$field] = $message;
					}
				}
			}
		} else {
			$this->request->data = $theme['settings'];
		}

		$this->set('arrayContext', $arrayContext);
		$this->set('theme', $theme);
		$this->Breadcrumb->push('/admin/system/themes');
		$this->Breadcrumb->push(__d('system', 'Settings for %s theme', $theme['name']), '#');
	}

}