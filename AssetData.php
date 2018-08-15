<?php
/**
 * This is a plugin for PicoCMS that provides Twig variables that represent the contents of an asset folder
 * or folders. The point of this is to allow the creation of templates that respond to the presence of files
 * in a web site's folder structure.
 *
 * Created by PhpStorm.
 * User: trevorskey
 * Date: 8/5/18
 * Time: 11:00 PM
 * @author Trevor Key
 * @license MIT
 * @version 1.0
 */

class AssetData extends AbstractPicoPlugin {
    const API_VERSION = 2;

    // Config Data
    protected $base_url;
    protected $site_base_folder = null;
    protected $asset_folder = "assets";
    protected $limit_asset_data = true;
    protected $max_dir_depth = 3;
    protected $render_yaml = false;
    protected $debug_mode = false;
    protected $folderize_non_index_files = true;

    // Working Data
    protected $assets_array = array();

    /*
     * onConfigLoaded
     * Reads in the configuration variables.
     */
    public function onConfigLoaded(&$config) {
        if (isset($config['base_url']))
            $this->base_url = $config['base_url'];
        if (isset($config['assets_folder']))
            $this->asset_folder = $config['assets_folder'];
        if (isset($config['asset_data_limit_by_location']))
            $this->limit_asset_data = $config['asset_data_limit_by_location'];
        if (isset($config['asset_data_max_depth']))
            $this->max_dir_depth = $config['asset_data_max_depth'];
        if (isset($config['asset_data_render_yaml']))
            $this->render_yaml = $config['asset_data_render_yaml'];
        if (isset($config['asset_data_debug_mode_enable']))
            $this->debug_mode = $config['asset_data_debug_mode_enable'];
        if (isset($config['asset_data_use_non_index_content_as_tld']))
            $this->folderize_non_index_files = $config['asset_data_use_non_index_content_as_tld'];

        if (isset($config['asset_data_site_folder']))
            $this->site_base_folder = $config['asset_data_site_folder'];
        else
            $this->site_base_folder = getcwd();
    }

    /*
     * onPageRendering
     * This is the point where we can influence Twig variables.
     * Might as well do all of the work here.
     */
    public function onPageRendering(&$templateName, array &$twigVariables)
    {
        $final_asset_folders = array();
        $currentPage = $this->getPico()->getCurrentPage();
        if (!is_array($this->asset_folder)) {
            $this->asset_folder = array($this->asset_folder);
        }
        if ($this->debug_mode) {
            print("<!-- AssetData found " . count($this->asset_folder) . " asset folders to scan:\n");
            var_dump($this->asset_folder);
            print("\n-->\n");
        }
        foreach ($this->asset_folder as $asset_folder) {
            if ($this->limit_asset_data && isset($currentPage) && !($currentPage['id'] === "index")) {
                if ($this->endsWith($currentPage['id'], '/index')) {
                    $asset_folder = $asset_folder . "/" . substr($currentPage['id'], 0, strlen($currentPage['id']) - 6);
                } else {
                    if ($this->folderize_non_index_files) {
                        $asset_folder = $asset_folder . "/" . $currentPage['id'];
                    } else {
                        $asset_folder = $asset_folder . "/" . substr($currentPage['id'], 0, strrpos($currentPage['id'], '/'));
                    }
                }
            }
            $asset_base = rtrim($this->site_base_folder, '/') . "/" . $asset_folder;
            $final_asset_folders[count($final_asset_folders)] = $asset_folder;
            $this->assets_array[$asset_folder] = $this->readDirStructure($asset_base, '', $this->max_dir_depth);
        }

        // No need to complicate Twig usages if only one asset folder is being scanned.
        if (count($final_asset_folders) == 1) {
            $twigVariables['asset_base'] = $final_asset_folders[0];
            $twigVariables['assets'] = $this->assets_array[$final_asset_folders[0]];
        } else {
            $twigVariables['asset_base'] = $this->asset_folder;
            $twigVariables['assets'] = $this->assets_array;
        }

        if ($this->debug_mode) {
            print("<!-- AssetData Plugin Final Output:\n");
            print("asset_base:\n");
            var_dump($twigVariables['asset_base']);
            print("\nassets:\n");
            var_dump($twigVariables['assets']);
            print(" -->\n");
        }
    }

    /*
     * readDirStructure (Private)
     * The idea is to return a structure such that it contains 2 keys: folders and files
     * The Files key points to an array of strings that are filenames.
     * The Folders key points to another dictionary where the keys are the folder names and each of those points to
     * a similar dictionary of Files/Folders.
     * Additionally, if YAML parsing is enabled and we encounter a .yml file, we'll add the contents of the file
     * to a yamls key and keep it out of the files list. (Why keep it out? Because if we process it here, presumably
     * no more processing needs to be done on it and it might get in the way of any processing done by a Twig template.)
     */
    private function readDirStructure($container_path, $dirname, $maxdepth) {
        if ($maxdepth == 0) return null;
        $fullpath = rtrim($container_path, '/') . "/" . $dirname;
        if (!is_dir($fullpath)) return null;
        $folder_contents = array(
            "folders" => array(),
            "files" => array()
        );
        if ($this->render_yaml) $folder_contents['yamls'] = array();

        if ($dh = opendir($fullpath)) {
            while (($filename = readdir($dh)) !== false) {
                $fullfilepath = $fullpath . "/" . $filename;
                if ($this->startsWith($filename, '.')) continue;
                if (is_dir($fullfilepath)) {
                    $folder_contents['folders'][$filename] = $this->readDirStructure($fullpath, $filename, $maxdepth - 1);
                } else {
                    if ($this->render_yaml && $this->endsWith($filename, ".yml")) {
                        $yamlparser = $this->getPico()->getYamlParser();
                        $filecontents = file_get_contents($fullfilepath);
                        $parsedfile = $yamlparser->parse($filecontents);
                        $folder_contents['yamls'][$filename] = $parsedfile;
                    } else
                        $folder_contents['files'][count($folder_contents['files'])] = $filename;
                }
            }
        } elseif ($this->debug_mode) {
            print("<!--\nUnable to read folder location: " . $fullpath . "\n-->\n");
        }
        sort($folder_contents['files']);
        ksort($folder_contents['folders']);
        return $folder_contents;
    }

    private function startsWith($test_string, $search)
    {
        $length = strlen($search);
        return (substr($test_string, 0, $length) === $search);
    }

    private function endsWith($test_string, $search)
    {
        $length = strlen($search);

        return $length === 0 ||
            (substr($test_string, -$length) === $search);
    }
}
