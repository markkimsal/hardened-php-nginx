<?php

$versions = json_decode(file_get_contents("versions.json"), true);
foreach ($versions['versions'] as $majorVersion => $definition) {
	//mkdir('php-fpm/' . $majorVersion);
	foreach($definition['variants'] as $variant) {
		echo "generating $majorVersion/$variant\n";
		$finalDir = 'php-fpm/' . $majorVersion . '/' . $variant;
		if(!is_dir($finalDir)) {
			mkdir($finalDir, 0775, true);
		}

		$versionTag = $definition['version'] . '-' . $variant;
		ob_start();
		include('Dockerfile.template');
		$templatedDockerfile = ob_get_contents();
	    ob_end_clean();
		file_put_contents($finalDir . '/Dockerfile', $templatedDockerfile);
		
		#copy fs foldes
		`cp -r .templatefs/* $finalDir/`;
		// $fsdir = new RecursiveDirectoryIterator(".templatefs");
		// foreach ($fsdir as $file) {
		//     echo $file->getFilename() . " got. \n";
		//     if(str_starts_with($file->getFilename(), ".")) {
		//         echo $file->getFilename() . " skipping. \n";
		//         continue;
		//     }
		//     $filename = $file->getPathname();
		//     $filename = explode('/', $filename);
		//     array_shift($filename);
		//     $filename = implode('/', $filename);
		//     if ($file->isDir()) {
		//         if(!is_dir($finalDir)) {
		//             mkdir($finalDir . '/' . $filename, 0775, true);
		//         }
		//     } else {
		//         echo $filename . "\n";
		//         var_dump($filename);
		//     }
		// }
	}
}

function builder_deps($platform) {

	global $versions;
	echo implode(" \\\n    ", $versions['builder_deps'][$platform]) . PHP_EOL;
}

function extension_deps() {
	global $versions;
	global $variant;
	$libdir = "/lib/x86_64-linux-gnu/";
	if (str_contains($variant, 'apline')) {
		$libdir = "/usr/lib/";
	}
	echo implode(" \\\n    ", array_map(function($lib) use ($libdir) {
		return $libdir.$lib;
	}, $versions['extension_deps'][$variant])) . " \\\n    " .$libdir;
}
