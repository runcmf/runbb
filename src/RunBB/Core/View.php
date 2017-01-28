<?php

/**
* Copyright (C) 2015-2016 FeatherBB
* based on code by (C) 2008-2015 FluxBB
* and Rickard Andersson (C) 2002-2008 PunBB
* License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
*/

namespace RunBB\Core;

use RunBB\Core\Interfaces\Container;
use RunBB\Exception\RunBBException;

class View
{
    protected $directories = [],
    $templates,
    $app,
    $data,
    $assets,
    $validation = [
        'page_number' => 'intval',
        'active_page' => 'strval',
        'is_indexed' => 'boolval',
        'admin_console' => 'boolval',
        'has_reports' => 'boolval',
        'paging_links' => 'strval',
        'footer_style' => 'strval',
        'fid' => 'intval',
        'pid' => 'intval',
        'tid' => 'intval'
    ];

    public $tplPath = '', $useTwig = false;

    /**
    * Constructor
    */
    public function __construct()
    {
        $this->data = new \RunBB\Helpers\Set();
        // Set default dir for view fallback
        $this->addTemplatesDirectory(ForumEnv::get('FORUM_ROOT') . 'View/', 10);
    }

    /********************************************************************************
    * Data methods
    *******************************************************************************/

    /**
    * Does view data have value with key?
    * @param  string  $key
    * @return boolean
    */
    public function has($key)
    {
        return $this->data->has($key);
    }

    /**
    * Return view data value with key
    * @param  string $key
    * @return mixed
    */
    public function get($key)
    {
        return $this->data->get($key);
    }

    /**
    * Set view data value with key
    * @param string $key
    * @param mixed $value
    */
    public function set($key, $value)
    {
        $this->data->set($key, $value);
    }

    /**
    * Set view data value as Closure with key
    * @param string $key
    * @param mixed $value
    */
    public function keep($key, \Closure $value)
    {
        $this->data->keep($key, $value);
    }

    /**
    * Replace view data
    * @param  array  $data
    */
    public function replace(array $data)
    {
        $this->data->replace($data);
    }

    /**
    * Clear view data
    */
    public function clear()
    {
        $this->data->clear();
    }

    /********************************************************************************
    * Resolve template paths
    *******************************************************************************/

    public function addTemplatesDirectory($data, $priority = 10)
    {
        $directories = (array) $data;
        foreach ($directories as $key => $tpl_dir) {
            if (is_dir($tpl_dir)) {
                $this->directories[(int) $priority][] = rtrim((string) $tpl_dir, DIRECTORY_SEPARATOR);
            }
        }
        return $this;
    }

    /**
    * Get templates directories ordered by priority
    * @return string
    */
    public function getTemplatesDirectory()
    {
        $output = [];
        if (count($this->directories) > 1) {
            ksort($this->directories);
        }
        foreach ($this->directories as $priority) {
            if (!empty($priority)) {
                foreach ($priority as $tpl_dir) {
                    $output[] = $tpl_dir;
                }
            }
        }
        return $output;
    }

    /**
     * Get fully qualified path to template file using templates base directory
     * @param string $file The template file pathname relative to templates base directory
     * @return string
     * @throws RunBBException
     */
    public function getTemplatePathname($file)
    {
        foreach ($this->getTemplatesDirectory() as $tpl_dir) {
            $pathname = realpath($tpl_dir . DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR));
            if (is_file($pathname)) {
                return (string) $pathname;
            }
        }
        throw new RunBBException("View cannot add template `$file` to stack because the template does not exist");
    }

    /********************************************************************************
    * Rendering
    *******************************************************************************/

    public function display($nested = true)
    {
//        if (User::get()) {
//            $this->setStyle(User::get()->style);
//        }
        return $this->fetch($nested);
    }

    protected function fetch($nested = true)
    {
        $data = [];
        $data = array_merge($this->getDefaultPageInfo(), $this->data->all(), (array) $data);
        $data['feather'] = true;
        $data['assets'] = $this->getAssets();
        $data = Container::get('hooks')->fire('view.alter_data', $data);

        return $this->render($data, $nested);
    }

    protected function render($data = null, $nested = true)
    {
        if($this->useTwig) {
            return $this->twigRender($data, $nested);
        }

        extract($data);
        ob_start();
        // Include view files
        if ($nested) {
            include $this->getTemplatePathname('header.php');
        }
        foreach ($this->getTemplates() as $tpl) {
            include $tpl;
        }
        if ($nested) {
            include $this->getTemplatePathname('footer.php');
        }

        $output = ob_get_clean();
        Response::getBody()->write($output);

        return Container::get('response');
    }

    protected function twigRender($data = null, $nested = true)
    {
        $data['nested'] = $nested;
        $data['pageTitle'] = Utils::generate_page_title($data['title'], $data['page_number']);
        $data['flashMessages'] = Container::get('flash')->getMessages();
        $data['style'] = View::getStyle();
        $data['navlinks'] = $this->buildNavLinks($data['active_page']);

        if (file_exists(ForumEnv::get('WEB_ROOT').'style/themes/'.View::getStyle().'/base_admin.css')) {
            $admStyle = '<link rel="stylesheet" type="text/css" href="'.Url::base_static().'/style/themes/'.View::getStyle().'/base_admin.css" />';
        } else {
            $admStyle = '<link rel="stylesheet" type="text/css" href="'.Url::base_static().'/style/imports/base_admin.css" />';
        }
        $data['admStyle'] = $admStyle;

        $templates = $this->getTemplates();
dump($templates);
        $tpl = trim(array_pop($templates));// get last in array
        $tpl = substr(str_replace(ForumEnv::get('FORUM_ROOT') . 'View/', '', $tpl), 0, -4);
        $tpl = '@forum/' . $tpl . '.html.twig';

        try {
            $output = Container::get('twig')->render($tpl, $data);
        } catch (\Twig_Error $e) {
            // try return to php template show error
            $this->useTwig = false;
            throw new RunBBException('Twig Exception, file: '.$e->getFile()
                .' line: '.$e->getLine().' message: '.$e->getMessage());
        }

        Response::getBody()->write($output);
        return Container::get('response');
    }
    /********************************************************************************
    * Getters and setters
    *******************************************************************************/

    /**
     * load assets for given style
     * @param $style
     */
    public function loadThemeAssets($style) {
        $dir = ForumEnv::get('WEB_ROOT').'style/themes/'.$style.'/';
        if(is_file($dir . 'bootstrap.php')) {
            $vars = include_once $dir . 'bootstrap.php';
            if(empty($vars)) {
                return;
            }
            foreach ($vars as $key => $assets) {
                if ($key === 'jsraw' || !in_array($key, ['js', 'jshead', 'css'])) {
                    continue;
                }
                foreach ($assets as $asset) {
                    $params = ($key === 'css') ? ['type' => 'text/css', 'rel' => 'stylesheet'] : (
                    ($key === 'js' || $key === 'jshead') ? ['type' => 'text/javascript'] : []
                    );
                    $this->addAsset($key, $asset, $params);
                }
            }
            $this->set('jsraw', $vars['jsraw']);
        }

        $this->useTwig = true;// FIXME config it

        $this->setStyle($style);
    }

    public function setStyle($style)
    {
        $this->tplPath = ForumEnv::get('WEB_ROOT').'style/themes/'.$style.'/';
        if (!is_dir($this->tplPath)) {
            throw new RunBBException('The style '.$style.' doesn\'t exist');
        }
        $this->data->set('style', (string) $style);
        $this->addTemplatesDirectory($this->tplPath.'/view', 9);

        // add path and alias if Twig enabled
        if($this->useTwig) {
            $loader = Container::get('twig')->getLoader();
            $loader->addPath($this->tplPath . 'view', 'forum');
        }
    }

    public function getStyle()
    {
        return $this->data['style'];
    }

    public function setPageInfo(array $data)
    {
        foreach ($data as $key => $value) {
            list($key, $value) = $this->validate($key, $value);
            $this->data->set($key, $value);
        }
        return $this;
    }

    public function getPageInfo()
    {
        return $this->data->all();
    }

    protected function validate($key, $value)
    {
        $key = (string) $key;
        if (isset($this->validation[$key])) {
            if (function_exists($this->validation[$key])) {
                $value = $this->validation[$key]($value);
            }
        }
        return [$key, $value];
    }

    public function addAsset($type, $asset, $params = [])
    {
        $type = (string) $type;
        if (!in_array($type, ['js', 'jshead', 'css', 'feed', 'canonical', 'prev', 'next'])) {
            throw new RunBBException('Invalid asset type : ' . $type);
        }
        if (in_array($type, ['js', 'jshead', 'css']) && !is_file(ForumEnv::get('WEB_ROOT').$asset)) {
            throw new RunBBException('The asset file ' . $asset . ' does not exist');
        }

        $params = array_merge(static::getDefaultParams($type), $params);
        if (isset($params['title'])) {
            $params['title'] = Utils::escape($params['title']);
        }
        $this->assets[$type][] = [
            'file' => (string) $asset,
            'params' => $params
        ];
    }

    public function getAssets()
    {
        return $this->assets;
    }

    public function addTemplate($tpl, $priority = 10)
    {
        $tpl = (array) $tpl;
        foreach ($tpl as $key => $tpl_file) {
            $this->templates[(int) $priority][] = $this->getTemplatePathname((string) $tpl_file);
        }
        return $this;
    }

    public function getTemplates()
    {
        $output = [];
        if (count($this->templates) > 1) {
            ksort($this->templates);
        }
        foreach ($this->templates as $priority) {
            if (!empty($priority)) {
                foreach ($priority as $tpl) {
                    $output[] = $tpl;
                }
            }
        }
        return $output;
    }

    public function addMessage($msg, $type = 'info')
    {
        if (Container::get('flash')) {
            if (in_array($type, ['info', 'error', 'warning', 'success'])) {
                Container::get('flash')->addMessage($type, (string) $msg);
            }
        }
    }

    public function __call($method, $args)
    {
        $method = mb_substr(preg_replace_callback('/([A-Z])/', function ($c) {
            return '_' . strtolower($c[1]);
        }, $method), 4);
        if (empty($args)) {
            $args = null;
        }
        list($key, $value) = $this->validate($method, $args);
        $this->data->set($key, $value);
    }

    protected function getDefaultPageInfo()
    {
        // Check if config file exists to avoid error when installing forum
        if (!Container::get('cache')->isCached('quickjump') && is_file(ForumEnv::get('FORUM_CONFIG_FILE'))) {
            Container::get('cache')->store('quickjump', \RunBB\Model\Cache::get_quickjump());
        }

        $title = Container::get('forum_settings') ? ForumSettings::get('o_board_title') : 'RunBB';

        $data = [
            'title' => Utils::escape($title),
            'page_number' => null,
            'active_page' => 'index',
            'focus_element' => null,
            'is_indexed' => true,
            'admin_console' => false,
            'page_head' => null,
            'paging_links' => null,
            'required_fields' => null,
            'footer_style' => null,
            'quickjump' => Container::get('cache')->retrieve('quickjump'),
            'fid' => null,
            'pid' => null,
            'tid' => null,
        ];

        if (is_object(User::get()) && User::get()->is_admmod) {
            $data['has_reports'] = \RunBB\Model\Admin\Reports::has_reports();
        }

        if (ForumEnv::get('FEATHER_SHOW_INFO')) {
            $data['exec_info'] = \RunBB\Model\Debug::get_info();
            if (ForumEnv::get('FEATHER_SHOW_QUERIES')) {
                $data['queries_info'] = \RunBB\Model\Debug::get_queries();
            }
        }

        return $data;
    }

    protected static function getDefaultParams($type)
    {
        switch ($type) {
            case 'js':
                return ['type' => 'text/javascript'];
            case 'jshead':
                return ['type' => 'text/javascript'];
            case 'css':
                return ['rel' => 'stylesheet', 'type' => 'text/css'];
            case 'feed':
                return ['rel' => 'alternate', 'type' => 'application/atom+xml'];
            case 'canonical':
                return ['rel' => 'canonical'];
            case 'prev':
                return ['rel' => 'prev'];
            case 'next':
                return ['rel' => 'next'];
            default:
                return [];
        }
    }

    protected function buildNavLinks($active_page = '')
    {
        $navlinks = [];

        $navlinks[] = [
            'id' => 'navindex',
            'active' => ($active_page == 'index') ? ' class="isactive"' : '',
            'href' => Url::base().'/forum',
            'text' => __('Index')
        ];

        if (User::get()->g_read_board == '1' && User::get()->g_view_users == '1') {
            $navlinks[] = [
                'id' => 'navuserlist',
                'active' => ($active_page == 'userlist') ? ' class="isactive"' : '',
                'href' => Router::pathFor('userList'),
                'text' => __('User list')
            ];
        }

        if (ForumSettings::get('o_rules') == '1' && (!User::get()->is_guest || User::get()->g_read_board == '1' || ForumSettings::get('o_regs_allow') == '1')) {
            $navlinks[] = [
                'id' => 'navrules',
                'active' => ($active_page == 'rules') ? ' class="isactive"' : '',
                'href' => Router::pathFor('rules'),
                'text' => __('Rules')
            ];
        }

        if (User::get()->g_read_board == '1' && User::get()->g_search == '1') {
            $navlinks[] = [
                'id' => 'navsearch',
                'active' => ($active_page == 'search') ? ' class="isactive"' : '',
                'href' => Router::pathFor('search'),
                'text' => __('Search')
            ];
        }

        if (User::get()->is_guest) {
            $navlinks[] = [
                'id' => 'navregister',
                'active' => ($active_page == 'register') ? ' class="isactive"' : '',
                'href' => Router::pathFor('register'),
                'text' => __('Register')
            ];
            $navlinks[] = [
                'id' => 'navlogin',
                'active' => ($active_page == 'login') ? ' class="isactive"' : '',
                'href' => Router::pathFor('login'),
                'text' => __('Login')
            ];
        } else {
            $navlinks[] = [
                'id' => 'navprofile',
                'active' => ($active_page == 'profile') ? ' class="isactive"' : '',
                'href' => Router::pathFor('userProfile', ['id' => User::get()->id]),
                'text' => __('Profile')
            ];
            if (User::get()->is_admmod) {
                $navlinks[] = [
                    'id' => 'navadmin',
                    'active' => ($active_page == 'admin') ? ' class="isactive"' : '',
                    'href' => Router::pathFor('adminIndex'),
                    'text' => __('Admin')
                ];
            }

            $navlinks[] = [
                'id' => 'navlogout',
                'active' => ($active_page == 'logout') ? ' class="isactive"' : '',
                'href' => Router::pathFor('logout', ['token' => Random::hash(User::get()->id.Random::hash(Utils::getIp()))]),
                'text' => __('Logout')
            ];
        }

        // Are there any additional navlinks we should insert into the array before imploding it?
        $hooksLinks = Container::get('hooks')->fire('view.header.navlinks', []);
        $extraLinks = ForumSettings::get('o_additional_navlinks')."\n".implode("\n", $hooksLinks);
        if (User::get()->g_read_board == '1' && ($extraLinks != '')) {
            if (preg_match_all('%([0-9]+)\s*=\s*(.*?)\n%s', $extraLinks."\n", $results)) {
                // Insert any additional links into the $links array (at the correct index)
                $num_links = count($results[1]);
                for ($i = 0; $i < $num_links; ++$i) {
                    array_splice($navlinks, $results[1][$i], 0, ['<li id="navextra'.($i + 1).'"'.(($active_page == 'navextra'.($i + 1)) ? ' class="isactive"' : '').'>'.$results[2][$i].'</li>']);
                }
            }
        }

        return $navlinks;
    }
}
