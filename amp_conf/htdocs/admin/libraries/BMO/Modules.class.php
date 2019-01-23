<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * This is a very basic interface to the existing 'module_functions' class.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;

use Carbon\Carbon;

class Modules extends DB_Helper{

	private static $count = 0;
	public $active_modules;
	private $moduleMethods = array();
	private $validLicense = null;
	private $functionIncLoaded = [];

	// Cache for XML objects
	private $modulexml = array();

	public function __construct($freepbx = null) {

		if ($freepbx == null) {
			throw new \Exception("Need to be instantiated with a FreePBX Object");
		}
		$this->FreePBX = $freepbx;

		if (!class_exists('module_functions')) {
			throw new \Exception("module_functions class missing? Bootstrap not run?");
		}

		$this->modclass = \module_functions::create();

		self::$count++;
		if(self::$count > 1) {
			throw new \Exception("The 'Modules' class has loaded more than once! This is a serious error!");
		}
	}

	/**
	 * Get all active modules
	 * @method getActiveModules
	 * @param  boolean          $cached Whether to cache the results.
	 * @return array                   array of active modules
	 */
	public function getActiveModules($cached=true) {
		// If session isn't authenticated, we don't care about modules.
		if (!defined('FREEPBX_IS_AUTH') || !FREEPBX_IS_AUTH) {
			$modules = $this->modclass->getinfo(false,MODULE_STATUS_ENABLED);
			$final = array();
			foreach($modules as $rawname => $data) {
				if(isset($data['authentication']) && $data['authentication'] == 'false') {
					$final[$rawname] = $data;
				}
			}
			$this->active_modules = $final;
		} else {
			if(empty($this->active_modules) || !$cached) {
				$this->active_modules = $this->modclass->getinfo(false, MODULE_STATUS_ENABLED);
			}
		}

		return $this->active_modules;
	}

	/**
	 * Get destinations of every module
	 * This function might be slow, but it works from within bmo
	 * @return array Array of destinations
	 */
	public function getDestinations() {
		return $this->FreePBX->Destinations->getAllDestinations();
	}

	/**
	 * Load all Function.inc.php files into FreePBX
	 */
	public function loadAllFunctionsInc() {
		$path = $this->FreePBX->Config->get("AMPWEBROOT");
		$modules = $this->getActiveModules(false); //TODO: is false wise here?
		foreach($modules as $rawname => $data) {
			if(in_array($rawname,$this->functionIncLoaded)) {
				continue;
			}
			$ifiles = get_included_files();
			$relative = $rawname."/functions.inc.php";
			$absolute = $path."/admin/modules/".$relative;
			$needs_zend = isset($data['depends']['phpcomponent']) && stristr($data['depends']['phpcomponent'], 'zend');
			if(file_exists($absolute)) {
				if ($needs_zend && class_exists('\Schmooze\Zend',false) && \Schmooze\Zend::fileIsLicensed($absolute) && !$this->loadLicensedFileCheck()) {
					continue;
				}
				$include = true;
				foreach($ifiles as $file) {
					if(strpos($file, $relative) !== false) {
						$include = false;
						break;
					}
				}
				if($include) {
					$this->functionIncLoaded[] = $rawname;
					include $absolute;
				}
			}
		}
		return true;
	}

	/**
	 * Try to load a functions.inc.php if not previously loaded
	 * @param  string $module The module rawname
	 */
	public function loadFunctionsInc($module) {
		if(in_array($module,$this->functionIncLoaded)) {
			return true;
		}
		if($this->checkStatus($module)) {
			$path = $this->FreePBX->Config->get("AMPWEBROOT");
			$ifiles = get_included_files();
			$relative = $module."/functions.inc.php";
			$absolute = $path."/admin/modules/".$relative;
			$data = \FreePBX::Modules()->getInfo($module);
			$needs_zend = isset($data[$module]['depends']['phpcomponent']) && stristr($data[$module]['depends']['phpcomponent'], 'zend');
			if(file_exists($absolute)) {
				if ($needs_zend && class_exists('\Schmooze\Zend',false) && \Schmooze\Zend::fileIsLicensed($absolute) && !$this->loadLicensedFileCheck()) {
					return false;
				}
				$include = true;
				foreach($ifiles as $file) {
					if(strpos($file, $relative) !== false) {
						$include = false;
						break;
					}
				}
				if($include) {
					$this->functionIncLoaded[] = $module;
					include $absolute;
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Check to make sure we have a valid license on the system if it's needed
	 * This is so that commercial modules wont crash the system
	 * @return boolean True if we can load the file, false otherwise
	 */
	public function loadLicensedFileCheck() {
		if(!is_null($this->validLicense)) {
			return $this->validLicense;
		}
		$licFileExists = glob ('/etc/schmooze/license-*.zl');
		if(!function_exists('zend_loader_install_license') || empty($licFileExists)) {
			$this->validLicense = false;
			return false;
		}

		$path = $this->FreePBX->Config->get("AMPWEBROOT");
		$sclass = $path."/admin/modules/sysadmin/functions.inc/Schmooze.class.php";
		if (file_exists($sclass) && !class_exists('\Schmooze\Zend',false)) {
			$this->validLicense = false;
			include $sclass;
		}
		if (!class_exists('\Schmooze\Zend')) {
			// Schmooze class is broken somehow. Accidentally deleted, possibly?
			$this->validLicense = false;
			return false;
		}
		if (!\Schmooze\Zend::hasValidLic()) {
			$this->validLicense = false;
			return false;
		}
		$this->validLicense = true;
		return true;
	}

	/**
	 * Get Signature
	 * @param string $modulename The raw module name
	 * @param bool $cached     Get cached data or update the signature
	 */
	public function getSignature($modulename,$cached=true) {
		return $this->modclass->getSignature($modulename,$cached);
	}

	/**
	 * String invalid characters from a class name
	 * @param string $module The raw module name.
	 * @param bool $fixcase If true (default), fix the case of the module to be Xyyyy
	 */
	public function cleanModuleName($module, $fixcase = true) {
		$module = str_replace("-","dash",$module);
		if ($fixcase) {
			$module = ucfirst(strtolower($module));
		}
		return $module;
	}

	/**
	 * Check to see if said module has method and is publicly callable
	 * @param {string} $module The raw module name
	 * @param {string} $method The method name
	 */
	public function moduleHasMethod($module, $method) {
		$this->getActiveModules(false);
		$module = $this->cleanModuleName($module);
		if(!empty($this->moduleMethods[$module]) && in_array($method, $this->moduleMethods[$module])) {
			return true;
		}
		$amods = array();
		if(is_array($this->active_modules)) {
			foreach(array_keys($this->active_modules) as $mod) {
				$amods[] = $this->cleanModuleName($mod);
			}
			if(in_array($module,$amods)) {
				try {
					$rc = new \ReflectionClass($this->FreePBX->$module);
					if($rc->hasMethod($method)) {
						$reflection = new \ReflectionMethod($this->FreePBX->$module, $method);
						if ($reflection->isPublic()) {
							$this->moduleMethods[$module][] = $method;
							return true;
						}
					}
				} catch(\Exception $e) {
					return false;
				}
			}
		}
		return false;
	}

	/**
	 * Get All Modules by module status
	 * @method getModulesByStatus
	 * @param  mixed            $status Can be: false, single status or arry of statuses
	 * @return array                     Array of modules
	 */
	public function getModulesByStatus($status=false) {
		return $this->modclass->getinfo(false, $status);
	}

	/**
	 * Get all modules that have said method
	 * @param {string} $method The method name to look for
	 */
	public function getModulesByMethod($method) {
		$this->getActiveModules(false);
		$amods = array();
		if(is_array($this->active_modules)) {
			foreach(array_keys($this->active_modules) as $mod) {
				$amods[] = $this->cleanModuleName($mod);
			}
		}
		$methods = array();
		foreach($amods as $module) {
			if($this->moduleHasMethod($module,$method)) {
				$methods[] = $module;
			}
		}
		return $methods;
	}

	/**
	 * Search through all active modules for a function that ends in $func.
	 * Pass it $opts and return whatever is returned in to an array with the
	 * retuning module name as the key
	 * Takes:
	 * @func variable	the function name that we are searching for. The module name
	 * 					will be appened to this
	 * @opts mixed		a variable or array that will be passed to the function being
	 * 					called , if its found
	 *
	 */
	public function functionIterator($func, &$opts = '') {
		$this->getActiveModules(false);
		$res = array();
		if(!empty($this->active_modules)) {
			foreach ($this->active_modules as $active => $mod) {
				$funct = $mod['rawname'] . '_' . $func;
				if (function_exists($funct)) {
					$res[$mod['rawname']] = $funct($opts);
				}
			}
		}

		return $res;
	}

	/**
	 * Return the BMO Class name for the page that has been requested
	 *
	 * This is used for GUI Hooks - for example, when a page is requested like
	 * 'config.php?display=pjsip&action=foo&other=wibble', this returns the class
	 * that generated the display 'pjsip'.
	 *
	 * This means that even if your module is called CamelCaseName, the class file
	 * must be called Camelcasename.class.php
	 *
	 * @param $page Page name
	 * @return bool|string Class name, or false
	 */
	public function getClassName($page = null) {
		if ($page == null)
			throw new \Exception("I can't find a module for a page that doesn't exist");

		// Search through all active modules..
		$mods = $this->getActiveModules(false);
		if(empty($mods)) {return false;}
		foreach ($mods as $key => $mod) {
			// ..and if we know about the menuitem that we've been asked..
			if (isset($mod['menuitems']) && is_array($mod['menuitems']) && isset($mod['menuitems'][$page])) {
				// ..is it a BMO Module?
				$path = $this->FreePBX->Config->get_conf_setting('AMPWEBROOT')."/admin/modules/";
				if (file_exists($path.$key."/".ucfirst($key).".class.php")) {
					return ucfirst($key);
				}
			}
		}
		return false;
	}

	/**
	 * Pass-through to modules_class->getinfo
	 */
	public function getInfo($modname=false, $status = false, $forceload = false) {
		return $this->modclass->getinfo($modname, $status, $forceload);
	}

	/**
	 * Boolean return for checking a module's status
	 * @param {string} $modname Module Raw Name
	 * @param {constant} $status  Integer/Constant, status to compare to
	 */
	public function checkStatus($modname,$status=MODULE_STATUS_ENABLED) {
		$modinfo = $this->getInfo($modname);
		if(!empty($modinfo[$modname]) && $modinfo[$modname]['status'] == $status) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Parse a modules XML from filesystem
	 *
	 * This function loads a modules xml file from the filesystem, and return
	 * a simpleXML object.  This explicitly does NOT care about the active or
	 * inactive state of the module. It also caches the object, so this can
	 * be called multiple times without re-reading and re-generating the XML.
	 *
	 * @param (string) $modname Raw module name
	 * @returns (object) SimpleXML Object.
	 *
	 * @throws Exception if module does not exist
	 * @throws Exception if module xml file is not parseable
	 */

	public function getXML($modname = false) {
		if (!$modname) {
			throw new \Exception("No module name given");
		}
		if($modname === 'builtin'){
			return $this->modulexml[$modname];
		}
		// Do we have this in the cache?
		if (!isset($this->modulexml[$modname])) {
			// We haven't. Load it up!
			$moddir = $this->FreePBX->Config()->get("AMPWEBROOT")."/admin/modules/$modname";
			if (!is_dir($moddir)) {
				throw new \Exception("$moddir is not a directory");
			}

			$xmlfile = "$moddir/module.xml";
			if (!file_exists($xmlfile)) {
				throw new \Exception("$xmlfile does not exist");
			}

			$this->modulexml[$modname] = simplexml_load_file($xmlfile);
		}

		// Return it
		return $this->modulexml[$modname];
	}

	/**
	 * Get the CACHED data from the last online check
	 *
	 * This will never request an update, no matter what.
	 *
	 * @return array
	 */
	public function getCachedOnlineData() {
		$modules = $this->modclass->getonlinexml(false, false, true);
		// Also grab the timestamp for when this was last updated
		$res = \FreePBX::Database()->query("select `time` FROM `module_xml` WHERE id = 'previous'")->fetchAll(\PDO::FETCH_ASSOC);
		if (!isset($res[0])) {
			$time = 0;
		} else {
			$time = $res[0]['time'];
		}
		$time = new \DateTime("@$time");
		return [ "timestamp" => $time, "modules" => $modules ];
	}

	/**
	 * Get List of upgradable modules
	 * @method getUpgradeableModules
	 * @param  [type]                $onlinemodules [description]
	 * @return [type]                               [description]
	 */
	public function getUpgradeableModules($onlinemodules) {
		// Our current modules on the filesystem
		//
		// Don't check for disabled modules. Refer to
		//    http://issues.freepbx.org/browse/FREEPBX-8380
		//    http://issues.freepbx.org/browse/FREEPBX-8628
		$local = $this->getInfo(false, [MODULE_STATUS_ENABLED, MODULE_STATUS_NEEDUPGRADE, MODULE_STATUS_BROKEN], true);
		$upgrades = [];

		// Loop through our current ones and see if new ones are available online
		foreach ($local as $name => $cur) {
			if (isset($onlinemodules[$name]) && isset($cur['version'])) {
				$new = $onlinemodules[$name];
				// If our current version is lower than the new version
				if (version_compare_freepbx($cur['version'], $new['version']) < 0) {
					// It's upgradeable.
					$upgrades[$name] = [
						'name' => $name,
						'local_version' => $cur['version'],
						'online_version' => $new['version'],
						'descr_name' => $new['name'],
					];
				}
			}
		}
		return $upgrades;
	}

	/**
	 * Announce that the calling function is deprecated
	 * @method deprecatedFunction
	 * @param  integer            $pos Position in the stack to start at
	 */
	public function deprecatedFunction($pos=1) {
		$trace = debug_backtrace(2);
		$function = $trace[$pos]['function'];
		$file =  $trace[$pos]['file'];
		$line =  $trace[$pos]['line'];
		freepbx_log(LOG_WARNING,'Depreciated Function '.$function.' detected in '.$file.' on line '.$line);
	}

	public function getOnlineJson($module = false, $override_json = false, $never_refresh = false){
		$now = Carbon::now()->timestamp;
		$oneHour = Carbon::createFromTimestamp($now)->addHour()->timestamp;
		$last = $this->getConfig('moduleJSONCache');
		$last = $last?$last:($now-1);
		$version = getversion();
		// we need to know the freepbx major version we have running (ie: 12.0.1 is 12.0)
		preg_match('/(\d+\.\d+)/', $version, $matches);
		$base_version = $matches[1];
		$skip_cache = $this->FreePBX->Config->get('MODULEADMIN_SKIP_CACHE');
		$moduleArray = $this->getAll('moduleArray');
		if ($now > $last && !$never_refresh && !$skip_cache || empty($moduleArray)) {
			if($override_json){
				$raw = $this->modclass->get_url_contents($override_json, "/modules-" . $base_version . ".json");
			}
			if(!$override_json || !$moduleArray){
				$raw = $this->modclass->get_remote_contents("/all-" . $base_version . ".json", true, true);
			}
			$moduleArray = json_decode($raw,true);
			$this->setMultiConfig($moduleArray, 'moduleArray');
			$this->setConfig($oneHour, 'moduleJSONCache');
		}

		if($module !== false){ 
			$ret = [];
			if(isset($moduleArray['modules'][$module])){
				$ret = $moduleArray['modules'][$module];
			}
			if(!isset($moduleArray['modules'][$module])){
				$moduleArray['modules'][$module] = $this->modclass->getinfo($module)[$module];
				$ret = $moduleArray['modules'][$module];
			}
			$edge = $this->checkEdge($moduleArray, $module);
			if($edge !== false){
				$ret = $edge;
			}
			$ret['previous'] = [];
			if(isset($moduleArray['previous'][$module])){
				$ret['previous'] = $moduleArray['previous'][$module];
			}
			$ret['conflicts'] = $this->checkBreaking($ret);
			return $ret;
		}
		return $moduleArray;
	}

	
	public function checkEdge($moduleArray, $module){
		$release = false;
		$edge = false;
		if(!$this->FreePBX->Config->get('MODULEADMINEDGE')){
			return false;
		}
		if(isset($moduleArray['edge'][$module])){
			$edge = $moduleArray['edge'][$module];
		}
		if(!$edge){
			return false;
		}
		if(isset($moduleArray['modules'][$module])){
			$release = $moduleArray['modules'][$module];
		}
		if(!$release){
			return $edge;
		}
		if(version_compare_freepbx($release['version'], $edge['version'], '<')){
			return false;
		}
		return $edge;
	}

	public function checkBreaking($moduleArray = []){
		$moduleArray = is_array($moduleArray)?$moduleArray:[];
		if(isset($moduleArray['rawname'])){
			$moduleArray = [$moduleArray];
		}
		$breaking = false;
		$messages = [];
		$replacements = [];
		if(empty($ModuleArray)){
			$moduleArray = $this->modclass->getinfo();
		}
		foreach($moduleArray as $arrayItem){
			if(!isset($arrayItem['rawname'])){
				continue;
			}
			$xml = json_encode($this->getXML($arrayItem['rawname']));
			$local = json_decode($xml, true);
			$current = isset($arrayItem['breaking'])? $arrayItem['breaking']:[];
			if(empty($current) && empty($local)){
				continue;
			}
			$breakingItems = $this->mergeBreaking($local, $current);
			$breakingItems = isset($breakingItems['breaking'])? $breakingItems ['breaking']:[];
			foreach ($breakingItems as $value) {
				$key = $value['type'];
				if($key == 'conflict'){
					$modInfo = $this->modclass->getinfo($value['rawname']);
					$modInfo = $modInfo[$value['rawname']];
					$status = $modInfo['status']?$modInfo['status']:0;
					$version = $modInfo['version'];
					$issueVersion = isset($value['version'])? $value['version']:false;
					if($status == MODULE_STATUS_ENABLED && $status == MODULE_STATUS_NEEDUPGRADE){
						$breaking = true;
						if($issueVersion){
							$versionConflict = version_compare_freepbx($version, $issueVersion, 'le');
						}
						$err = sprintf(_("The module %s conflicts with this module. Having both installed may cause issues with the functionality of your system."), $value['rawname']);
						if(isset($value['errormessage']) && !empty($value['errormessage'])){
							$err = str_replace('RAW_NAME', $value['rawname'], $value['errormessage']);
						}
						if($issueVersion && $versionConflict){
							$err = _(sprintf(_("The module %s at version %s or higher conflicts with this module and may break the functionality of your system. Your current installed version is %s"),$value['rawname'],$issueVersion, $version));
							if (isset($value['versionerrormessage']) && !empty($value['versionerrormessage'])) {
								$err = str_replace('RAW_NAME', $value['rawname'], $value['versionerrormessage']);
							}
						}
						$messages[$arrayItem['rawname']][] = $err;
					}
				}
				if ($key == 'deprecated') {
					$modInfo = $this->modclass->getinfo($arrayItem['rawname']);
					if (!isset($modInfo[$arrayItem['rawname']])) {
						continue;
					}
					$modInfo = $modInfo[$arrayItem['rawname']];
					$status = $modInfo['status'] ? $modInfo['status'] : 0;
					$version = $modInfo['version'];
					$issueVersion = isset($value['version']) ? $value['version'] : false;
					if ($status == MODULE_STATUS_ENABLED || $status == MODULE_STATUS_NEEDUPGRADE) {
						if ($issueVersion) {
							$versionConflict = version_compare_freepbx($version, $issueVersion, 'ge');
						}
						if($issueVersion && !$versionConflict){
							continue;
						}
						$breaking = true;
						$replacement = isset($value['replace'])?$value['replace']:false;
						$error = sprintf(_("The module %s has been deprecated and may not be maintained in the future. This may cause security or functionality issues."), $value['rawname']);
						if (isset($value['errormessage']) && !empty($value['errormessage'])) {
							$error = str_replace('RAW_NAME', $value['rawname'], $value['errormessage']);
						}
						if($replacement){
							$error = sprintf(_("The module %s is deprecated and has been replaced by %s."), $value['rawname'], $replacement);
							if (isset($value['replaceerrormessage']) && !empty($value['replaceerrormessage'])) {
								$error = str_replace(['RAW_NAME','REPLACE_NAME'], [$value['rawname'], $value['replacement']], $value['errormessage']);
							}
							$replacements[$arrayItem['rawname']][] = $replacement;

						}
						$messages[$arrayItem['rawname']][] = $error;
					}
				}
			}
		}
			
		return ['status' => $breaking, 'issues' => $messages, 'replacements' => $replacements];
	}

	public function mergeBreaking($local, $remote){
		$local = is_array($local)?$local:[];
		$remote = is_array($remote)?$remote:[];
		$local = isset($local[0]['rawname'])?$local[0]:$local;
		$remote = isset($remote[0]['rawname'])?$remote[0]:$remote;
		$local['version'] = isset($local['version']) ? $local['version'] : '0.0';
		$remote['version'] = isset($remote['version']) ? $remote['version'] : '0.0';
		if (version_compare_freepbx($local['version'], $remote['version'], 'ge')) {
			return $local;
		}
		return $remote;
	}
}
