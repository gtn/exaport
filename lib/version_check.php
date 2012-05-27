<?php
	
if (!version_compare(PHP_VERSION, '5.2.0', '>=')) {
	print_header(get_string('error'));

	echo '<div style="text-align: center; padding-top: 40px;"><a href="http://gophp5.org" title="Support GoPHP5.org">
<img src="http://gophp5.org/sites/gophp5.org/buttons/goPHP5-283x100.png" 
height="100" width="283" alt="Support GoPHP5.org" />
</a></div>';

   	print_error("version_5.2.0_needed", "block_exaport");
}
