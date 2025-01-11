<!-- It is much easier to do parsing of YAML in PHP than in .sh; the standard way to do YAML parsing
in a shell script is to call yq, but yq requires different executables for different architectures. 
Given that the YAML parsing is already in PHP, it seemed easier to do the whole thing in PHP rather 
than split the work between two scripts -->
<?php

$MW_HOME = getenv("MW_HOME");
$MW_VERSION = getenv("MW_VERSION");
$MW_VOLUME = getenv("MW_VOLUME");
$MW_ORIGIN_FILES = getenv("MW_ORIGIN_FILES");
$path = $argv[1];

$yamlData = yaml_parse_file($path);

foreach (["skins", "extensions"] as $type) {
    foreach ($yamlData[$type] as $obj) {
        $name = key($obj);
        $data = $obj[$name];
        
        $repository = $data['repository'] ?? null;
        $commit = $data['commit'] ?? null;
        $branch = $data['branch'] ?? null;
        $patches = $data['patches'] ?? null;
        $persistentDirectories = $data['persistent-directories'] ?? null;
        $additionalSteps = $data['additional steps'] ?? null;
        $bundled = $data['bundled'] ?? false;

        if (!$bundled) {
            $gitCloneCmd = "git clone ";
            
            if ($repository === null) {
                $repository = "https://github.com/wikimedia/mediawiki-$type-$name";
                if ($branch === null) {
                    $branch = $MW_VERSION;
                    $gitCloneCmd .= "--single-branch -b $branch ";
                }
            }
            
            $gitCloneCmd .= "$repository $MW_HOME/$type/$name";
            $gitCheckoutCmd = "cd $MW_HOME/$type/$name && git checkout -q $commit";

            exec($gitCloneCmd);
            exec($gitCheckoutCmd);

            if ($patches !== null) {
                foreach ($patches as $patch) {
                    $gitApplyCmd = "cd $MW_HOME/$type/$name && git apply /tmp/$patch";
                    exec($gitApplyCmd);
                }
            }
        }

        if ($additionalSteps !== null) {
            foreach ($additionalSteps as $step) {
                if ($step === "composer update") {
                    $composerUpdateCmd = "cd $MW_HOME/$type/$name && composer update --no-dev";
                    exec($composerUpdateCmd);
                } elseif ($step === "git submodule update") {
                    $submoduleUpdateCmd = "cd $MW_HOME/$type/$name && git submodule update --init";
                    exec($submoduleUpdateCmd);
                }
            }
        }

        if ($persistentDirectories !== null) {
            exec("mkdir -p $MW_ORIGIN_FILES/canasta-$type/$name");
            foreach ($directory as $persistentDirectories) {
                exec("mv $MW_HOME/canasta-$type/$name/$directory $MW_ORIGIN_FILES/canasta-$type/$name/");
                exec("ln -s $MW_VOLUME/canasta-$type/$name/$directory $MW_HOME/canasta-$type/$name/$directory");
            }
        }
    }
}

?>
