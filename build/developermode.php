<?php

defined('MOODLE_INTERNAL') || die();

call_user_func(function() {
	$servicesFile = __DIR__.'/../db/services.php';

	if (file_exists($servicesFile)) {
		if (!is_writable($servicesFile)) {
			// no change possible
			return;
		}
		if (filemtime($servicesFile) == ($time = filemtime(__DIR__.'/../externallib.php'))) {
			// no change required
			// return;
		}
	}

	$services = array(
		'exaportservices' => array(
			'requiredcapability' => '',
			'restrictedusers' => 0,
			'enabled' => 1,
			'shortname' => 'exaportservices',
			'functions' => [],
		),
	);

	$functions = [];

	$doku = '';

	extract($GLOBALS);
	require_once __DIR__.'/../externallib.php';

	$rc = new ReflectionClass('block_exaport_external');
	$methods = $rc->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
	foreach ($methods as $method) {
		if (!preg_match('!@ws-type-(read|write)!', $method->getDocComment(), $matches)) {
			continue;
		}

		$description = preg_replace('!^[/\t \\*]+!m', '', $method->getDocComment());
		$description = trim(preg_replace('!@.*!sm', '', $description));

		$func = 'block_exaport_'.$method->getName();

		$functions[$func] = [                             // web service function name
			'classname' => 'block_exaport_external',         // class containing the external function
			'methodname' => $method->getName(), // external function name, strip block_exacomp_ for function name
			'classpath' => 'blocks/exaport/externallib.php', // file containing the class/external function
			'description' => $description,                   // human readable description of the web service function
			'type' => $matches[1],                   // database rights of the web service function (read, write)
		];

		$services['exaportservices']['functions'][] = $func;

		// doku
		$doku .= "<h2>$func</h2>\n";
		$doku .= "<div>$description</div>\n";
		$doku .= "<div>type: $matches[1]</div>\n";


		$paramMethod = $rc->getMethod($method->getName().'_parameters');
		/* @var external_function_parameters $params */
		$params = $paramMethod->invoke(null)->keys;
		$doku .= "Params: <table>\n";
		foreach ($params as $paramName => $paramInfo) {
			$doku .= "<tr>\n";
			$doku .= '<td>'.$paramName."</td>\n";
			$doku .= '<td>'.$paramInfo->type."</td>\n";
			$doku .= '<td>'.($paramInfo->allownull ? 'null' : 'not null')."</td>\n";
			$doku .= '<td>'.($paramInfo->required ? 'required' : 'optional')."</td>\n";
			if (!$paramInfo->required) {
				ob_start();
				var_dump($paramInfo->default);
				$default = ob_get_clean();
				$doku .= '<td>default: '.$default."</td>\n";
			} else {
				$doku .= '<td>'."</td>\n";
			}
			$doku .= '<td>'.$paramInfo->desc."</td>\n";
		}
		$doku .= "</table>\n";

		$returnMethod = $rc->getMethod($method->getName().'_returns');
		/* @var external_description $returns */
		$returns = $returnMethod->invoke(null);

		$recursor = function($o) use (&$recursor) {
			if ($o instanceof external_multiple_structure) {
				$ret = [];
				$ret[] = $recursor($o->content);
				if ($o->desc) {
					$ret[] = '... '.$o->desc.' ...';
				} else {
					$ret[] = '...';
				}
				return $ret;
			} elseif ($o instanceof external_single_structure) {
				$data = [];
				foreach ($o->keys as $paramName => $paramInfo) {
					if ($paramInfo instanceof external_value) {
						$data[$paramName] = $paramInfo->type.
							' '.($paramInfo->allownull ? 'null' : 'not null').
							' ('.$paramInfo->desc.')';
					} elseif ($paramInfo instanceof external_multiple_structure || $paramInfo instanceof external_single_structure) {
						$data[$paramName] = $recursor($paramInfo);
					} else {
						die('o');
					}
				}

				return $data;
			} else {
				die('x');
				$doku .= get_class($o);
			}
		};
		$data = $recursor($returns);

		$doku .= "Returns:<pre>".json_encode($data, JSON_PRETTY_PRINT)."</pre>\n";
	}

	// save to services.php
	$content = "<?php\n\n";
	$content .= '$functions = '.var_export($functions, true).";\n\n";
	$content .= '$services = '.var_export($services, true).";\n\n";
	file_put_contents($servicesFile, $content);
	touch($servicesFile, $time);

	// save doku
	$doku = '<style>
		table {
			border-collapse: collapse;
		}
		td {
			border: 1px solid black;
			padding: 2px 5px;
		}
	</style>'.$doku;

	file_put_contents(__DIR__.'/services.htm', $doku);
	touch(__DIR__.'/services.htm', $time);
});
