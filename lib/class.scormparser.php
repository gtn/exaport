<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require_once $CFG->dirroot.'/mod/scorm/datamodels/scormlib.php';

/**
 * SCORMParser: Parsing a SCORM File
 */
class SCORMParser {
	var $error = false;
	var $errorMsg = '';
	var $warning = false;
	var $warningMsg = '';
	var $dir = '';
	var $deleteFiles = false;

	/**
	 * setError(): set the error message
	 *
	 * @param msg The error-message
	 */
	function setError($msg) {
		$this->error = true;
		$this->errorMsg .= "Error: " . $msg . "\n";
	}

	/**
	 * setWarning(): set the warning message
	 * even with a warning message the file can be parsed
	 *
	 * @param msg The warning-message
	 */
	function setWarning($msg) {
		$this->warning = true;
		$this->warningMsg .= "Warning: " . $msg . "\n";
	}

	/**
	 * isError(): check if there was an error at parsing
	 *
	 * @return boolean error
	 */
	function isError() {
		return $this->error;
	}

	/**
	 * isWarning(): check if there was a warning at parsing
	 *
	 * @return boolean warning
	 */
	function isWarning() {
		return $this->warning;
	}

	/**
	 * getError(): returns the error string
	 *
	 * @return Error message
	 */
	function getError() {
		return $this->errorMsg;
	}

	/**
	 * getWarning(): returns the warning string
	 *
	 * @return Warning message
	 */
	function getWarning() {
		return $this->warningMsg;
	}

	/**
	 * parse(): Parses an SCORM-File
	 *
	 * @param msg The location of the imsmanifest.xml
	 * @return The page-tree
	 */
	function parse($scormfile) {
		$tree = array();
		
		// Check if file exists and extract path from filename
		if(preg_match('/^(.*)imsmanifest.xml$/', $scormfile, $regs) && is_file($scormfile)) {
			$this->dir = $regs[1];
			$imsfile = $regs[0];
		}
		else {
			$this->setError("File " . $scormfile . " is no SCORM-File.");
			return false;
		}

		// read content of file, parse the xml to an array and parse the SCORM-File if there is a root-element
		$xmlstring = file_get_contents($imsfile);						// read content of file
		$objXML = new xml2Array();
		$arrOutput = $objXML->parse($xmlstring);						 // parse the xml to an array
		if($arrOutput === false) {
			$this->setError($objXML->error);
			return false;
		}
		else if(count($arrOutput) == 1) {
			$manifest = $this->parse_manifest(array_shift($arrOutput));  // parse the manifest (if a manifest exists)
		}
		else {
			$this->setError("XML not well formed");
			return false;
		}

		// merge the organisation with the resources
		if(isset($manifest["organization"]) && isset($manifest["resources"]))
			$tree = $this->combine($manifest["organization"], $manifest["resources"]);

		// if there was an error at parsing, false is returned, else the tree
		if($this->error)
			return false;

		return $tree;
	}
	
	/**
	 * combine(): Merges the organisation with the resources
	 *
	 * @param organizations The organization tree
	 * @param resources The resources tree
	 * @return The combined tree
	 */
	function combine($organizations, $resources) {
		$elements = array();
		foreach($organizations as $organization) {
			$element = array();
			
			// If the page is not visible, don't use it
			if( (!isset($organization["data"]["ISVISIBLE"])) ||
				( isset($organization["data"]["ISVISIBLE"]) &&
				  ( ($organization["data"]["ISVISIBLE"] == 'true') ||
					($organization["data"]["ISVISIBLE"] == '1') ) ) ) {

				// if there is no Organization title, use "SCORM Import"
				if(isset($organization['title']))
					$element['data']['title'] = $organization['title'];
				else
					$element['data']['title'] = 'SCORM Import';
				
				if(isset($organization['identifier']))
					$element['data']['identifier'] = $organization['identifier'];
					
				if(isset($organization["data"]["ID"]))
					$element['data']['id'] = $organization["data"]["ID"];

				if(isset($organization["data"]["IDENTIFIERREF"])) { // if the IDENTIFIERREF is set...
					if(isset($resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"])) { // ... and the resource exists
						// if the file exists and the type is 'webcontent' ist - that means, it is content, that can be hosted or launched by a Webbrowser. (the type "webcontent" is mandatory)
						$element['data']['extlink'] = false;
						if(count($resources[$organization["data"]["IDENTIFIERREF"]]["files"]) == 0) {
							// no local resources -> link!
							$element['data']['url'] = $resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"];
							$element['data']['extlink'] = true;
						}
						else if(is_file($this->dir . $resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"]) &&
							($resources[$organization["data"]["IDENTIFIERREF"]]["info"]["TYPE"] == 'webcontent')) {
							// check every file on which the resource depends
							foreach($resources[$organization["data"]["IDENTIFIERREF"]]["files"] as $file) {
								if(!is_file($this->dir . $file))
									$this->setError("File ". $this->dir . $file . " not found");
							}
							foreach($resources[$organization["data"]["IDENTIFIERREF"]]["dependency"] as $dependency) {
								if(!isset($resources[$dependency]))
									$this->setError("Dependent Resource $dependency in Element ".$organization["data"]["IDENTIFIER"]." not found!");
								foreach($resources[$dependency]["files"] as $file) {
									if(!is_file($this->dir . $file))
										$this->setError("File ". $this->dir . $file . " not found");
								}
							}
							$element['data']['url'] = $resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"];
						}
						else {
							$this->setError("File " . $resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"] . " in Element " . $organization["data"]["IDENTIFIER"] . " not found.");
						}
					}
					else {
						$this->setError("Ressource " . $organization["data"]["IDENTIFIERREF"] . " in Element " . $organization["data"]["IDENTIFIER"] . " not found.");
					}
				}
			}
			
			// recursive if subpages exist
			if(isset($organization["items"])) {
			   $element["items"] = $this->combine($organization["items"], $resources);
			}
			$elements[] = $element;
			unset($element);
		}
		return $elements;
	}

	/**
	 * parse_manifest(): Parses the manifest-tag
	 *
	 * @param element: The element MANIFEST and the subtree
	 * @return The parsed tree
	 */
	function parse_manifest($element) {
		$manifest = array();
		// The outer element is one <MANIFEST>
		if($element["name"] == 'MANIFEST') {
			/* A <manifest> can have the following children:
				<metadata> (0 or 1 time)
				<organizations> (1 time)
				<resources> (1 time)
				<manifest> (0 to many times)
				Call the right subfunction for every element
			*/
			foreach($element["children"] as $child) {
				switch($child["name"]) {
					case 'RESOURCES':	   $manifest["resources"] = $this->getResources($child["children"]);
											break;
					case 'ORGANIZATIONS':   if(isset($child["children"]))
											$manifest["organization"] = $this->getOrganizations($child["children"]);
											break;
					case 'METADATA':		if(isset($child["children"]))
											$manifest["metadata"] = $this->getMetadata($child["children"]);
											break;
					case 'MANIFEST':		$submanifest = $this->parse_manifest($child["children"]);
											$manifest["metadata"] += $submanifest["metadata"];
											$manifest["organization"] = array_merge($manifest["organization"], $submanifest["organization"]); // identifier des arrays sind egal, deshalb array_merge
											$manifest["resources"] += $submanifest["resources"]; //identifier des arrays m�ssen erhalten bleiben, deshalb +
											break;
					default:				$this->setWarning("Missed Tag '" . $child["name"] . "' inside <manifest>");
											break;
				}
			}
		}
		return $manifest;
	}

	
	/**
	 * getOrganizations(): Parses the three at the ORGANIZATIONS element
	 *
	 * @param element: The element ORGIANIZATIONS and the subtree
	 * @return The organization-tree
	 */
	function getOrganizations($elements) {
		/* It's possible that the ORGANIZATIONS-Element has no Subelements,
			this is the case when we have a Ressource-Package.
			ORGANIZATIONS-Element can ONLY have ORGANIZATION as Subeleement: */
		$organization = array();
		foreach($elements as $element) {
			// identifier!!!
			if($element["name"] == 'ORGANIZATION') {
				// interpret each subelement into the $new_organization[] and add it to the $organization
				$new_organization = array();
		 		if(isset($element["attrs"]["IDENTIFIER"])) {
		 			$new_organization["identifier"] = $element["attrs"]["IDENTIFIER"];
		 		}
				foreach($element["children"] as $subelement) {
					switch($subelement["name"]) {
						case 'TITLE':	   $new_organization["title"] = $subelement["tagData"];
											break;
						case 'ITEM':		$new_organization["items"][addslashes($subelement["attrs"]["IDENTIFIER"])] = $this->recItemSearch($subelement);
											break;
						case 'METADATA':	if(isset($subelement["children"]))
												$new_organization["metadata"] = $this->getMetadata($subelement["children"]);
											break;
						default:			$this->setWarning("Missed Tag '" . $subelement["name"] . "' inside ORGANIZATION");
											break;
					}
				}
				$organization[] = $new_organization;
				unset($new_organization);
			}
			else {
				$this->setWarning("Missed Tag '" . $element["name"] . "' inside <organizations>");
			}
		}
		return $organization ;
	}

	/**
	 * recItemSearch(): Parses the ITEM and children (also ITEMs)
	 *
	 * @param element: The element ITEM and the subtree
	 * @return The items
	 */
	 function recItemSearch($elements) {
		$items = array();
		$items["data"] = $elements["attrs"];
		if(array_key_exists('children', $elements)) {
			foreach($elements["children"] as $subelement) {
				switch($subelement["name"]) {
					case 'TITLE':	   $items["title"] = $subelement["tagData"];
										break;
					case 'ITEM':		$items["items"][addslashes($subelement["attrs"]["IDENTIFIER"])] = $this->recItemSearch($subelement);
										break;
					case 'METADATA':	if(isset($subelement["children"]))
											$items["metadata"] = $this->getMetadata($subelement["children"]);
										break;
					default:			$this->setWarning("Missed Tag '" . $subelement["name"] . "' inside ITEM");
										break;
				}
			}
		}
		return $items;
	}

	/**
	 * getResources(): Parses the RESOURCES and children (also ITEMs)
	 *				 XML:BASE (realative pathoffset) is not implemented yet.
	 *
	 * @param element: The element RESOURCES and the subtree
	 * @return The resources
	 */
	function getResources($elements) {
		$resources = array();
		// Element RESOURCES can only have RESOURCE-Elements as children
		if(count($elements) > 0) {
			foreach($elements as $element) {
				switch($element["name"]) {
					case 'RESOURCE': $resources[addslashes($element["attrs"]["IDENTIFIER"])] = $this->getResource($element);
									 break;
					default:		 $this->setWarning("Missed Tag '" . $element["name"] . "' inside RESOURCES");
									 break;
				}
			}
		}
		return $resources;
	}

	/**
	 * getResources(): Parses the RESOURCE-element
	 *
	 * @param element: The element RESOURCE
	 * @return The resource with dependencies
	 */
	function getResource($element) {
		$resource = array();
		$resource["info"] = $element["attrs"];
		$resource["dependency"] = array();
		$resource["files"] = array();
		if(isset($element["children"])) {
			foreach($element["children"] as $subelement) {
				switch($subelement["name"]) {
					case 'FILE':		if(isset($subelement['attrs']['HREF'])) {
											if(is_file($this->dir . $subelement['attrs']['HREF']))
												$resource["files"][] = $subelement['attrs']['HREF'];
											// if the file doen't exist, don't produce an error. maybe the ressource isn't needed and the user forgot to delete it in the resource tree. if it's needed it's checked afterwards
										}
										break;
					case 'DEPENDENCY':  if(isset($subelement['attrs']['IDENTIFIERREF']))
											$resource["dependency"][] = $subelement['attrs']['IDENTIFIERREF'];
										break;
					case 'METADATA':	if(isset($element["children"]))
											$resource["metadata"] = $this->getMetadata($subelement["children"]);
										break;
					default:			$this->setWarning("Missed Tag '" . $subelement["name"] . "' inside RESOURCE");
										break;
				}
			}
		}
		return $resource;
	}
	
	/**
	 * getMetadata(): Parses the METADATA. Empty function, the Metadata of the SCORM-file is not needed yet.
	 *
	 * @param element: The element METADATA and subtree
	 * @return NULL
	 */
	function getMetadata($elements) {
		return NULL;
	}
}
