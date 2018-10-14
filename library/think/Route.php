<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

/**
 * 笔记
 * 1.$option键
 *      complete_match ：是否完全匹配
 *      method ：请求方式
 *      ext ：伪静态后缀检测
 *      deny_ext ：禁止的静态后缀
 *      V5.0.7版本以上，ext 和 deny_ext 参数允许设置为空，分别表示不允许任何后缀以及必须使用后缀访问。
 *      domain ：支持使用完整域名或者子域名进行检测
 *      before_behavior、after_behavior ：前置行为（检测）（支持使用行为对路由进行检测是否匹配，如果行为方法返回false表示当前路由规则无效。eg：Route::get('user/:id','index/User/read',['before_behavior'=>'\app\index\behavior\UserCheck']);）、后置行为（执行）（可以为某个路由或者某个分组路由定义后置行为执行，表示当路由匹配成功后，执行的行为）
 *      https ：是否https请求
 *      callback ：自定义检测方法
 *      merge_extra_vars ： 合并额外参数，通常用于完整匹配的情况，如果有额外的参数则合并作为变量值，例如： Route::get('new/:name$','News/read',['merge_extra_vars'=>true]);
 http://serverName/new/thinkphp/hello会被匹配到，并且name变量的值为 thinkphp/hello
 *      bind_model ：绑定模型（v5.0.1+）
 *      cache ：请求缓存（v5.0.1+），值为缓存的秒数
 *      param_depr ：路由参数分隔符（v5.0.2+）
 *      ajax：Ajax检测（v5.0.2+）
 *      pjax：Pjax检测（v5.0.2+)
 *
 *      name、var、prefix：不清楚用途
 *      only/allow：表示允许的操作，eg:Route::resource('blog','index/blog',['only'=>['index','read','edit','update']]);不知道两个的区别
 *            
 *      except：表示排除的操作，eg:Route::resource('blog','index/blog',['except'=>['index','delete']]);
 *      status：判断是否重定向时用到，301
 *      
 *      __url__ ： 如果要对整个URL进行规则检查，可以进行__url__ 变量规则，例如：
 *      定义GET请求路由规则 并设置完整URL变量规则
 *      Route::get('new/:id','News/read',[],['__url__'=>'new\/\w+$']);
     
 * 2.特殊路由规则/组合变量
 *      如果你的路由规则比较特殊，可以在路由定义的时候使用组合变量。
 *      eg:Route::get('item-<name>-<id>','product/detail',[],['name'=>'\w+','id'=>'\d+']);
 *      组合变量的优势是路由规则中没有固定的分隔符，可以随意组合需要的变量规则，例如路由规则改成如下一样可以支持：
 *      Route::get('item<name><id>','product/detail',[],['name'=>'[a-zA-Z]+','id'=>'\d+']);
 *      Route::get('item@<name>-<id>','product/detail',[],['name'=>'\w+','id'=>'\d+']);
 *      如果需要使用可选变量，则可以使用：
 *      Route::get('item-<name><id?>','product/detail',[],['name'=>'[a-zA-Z]+','id'=>'\d+']);
 *
 * 3.App.php 文件中URL路由检测会调用rules、import、check、parseUrl四个函数
 *
 * 
 */
namespace think;

use think\exception\HttpException;

class Route
{
    // 路由规则
    private static $rules = [
        'get'     => [],
        'post'    => [],
        'put'     => [],
        'delete'  => [],
        'patch'   => [],
        'head'    => [],
        'options' => [],
        '*'       => [],
        'alias'   => [],
        'domain'  => [],
        'pattern' => [],
        'name'    => [],
    ];

    // REST路由操作方法定义
    private static $rest = [
        'index'  => ['get', '', 'index'],
        'create' => ['get', '/create', 'create'],
        'edit'   => ['get', '/:id/edit', 'edit'],
        'read'   => ['get', '/:id', 'read'],
        'save'   => ['post', '', 'save'],
        'update' => ['put', '/:id', 'update'],
        'delete' => ['delete', '/:id', 'delete'],
    ];

    // 不同请求类型的方法前缀
    private static $methodPrefix = [
        'get'    => 'get',
        'post'   => 'post',
        'put'    => 'put',
        'delete' => 'delete',
        'patch'  => 'patch',
    ];

    // 子域名
    private static $subDomain = '';
    // 域名绑定
    private static $bind = [];
    // 当前分组信息
    private static $group = [];
    // 当前子域名绑定
    private static $domainBind;
    private static $domainRule;
    // 当前域名
    private static $domain;
    // 当前路由执行过程中的参数
    private static $option = [];

    /**
     * 注册变量规则
     * @access public
     * @param string|array  $name 变量名
     * @param string        $rule 变量规则
     * @return void
     */
    public static function pattern($name = null, $rule = '')
    {
        if (is_array($name)) {
            self::$rules['pattern'] = array_merge(self::$rules['pattern'], $name);
        } else {
            self::$rules['pattern'][$name] = $rule;
        }
    }

    /**
     * 注册子域名部署规则
     * @access public
     * @param string|array  $domain 子域名
     * @param mixed         $rule 路由规则
     * @param array         $option 路由参数
     * @param array         $pattern 变量规则
     * @return void
     */
    //mc todo
    public static function domain($domain, $rule = '', $option = [], $pattern = [])
    {
        if (is_array($domain)) {
            foreach ($domain as $key => $item) {
                self::domain($key, $item, $option, $pattern);
            }
        } elseif ($rule instanceof \Closure) {
            // 执行闭包
            self::setDomain($domain);
            call_user_func_array($rule, []);
            self::setDomain(null);
        } elseif (is_array($rule)) {
            self::setDomain($domain);
            self::group('', function () use ($rule) {
                // 动态注册域名的路由规则
                self::registerRules($rule);
            }, $option, $pattern);
            self::setDomain(null);
        } else {
            self::$rules['domain'][$domain]['[bind]'] = [$rule, $option, $pattern];
        }
    }

    private static function setDomain($domain)
    {
        self::$domain = $domain;
    }

    /**
     * 设置路由绑定
     * @access public
     * @param mixed     $bind 绑定信息
     * @param string    $type 绑定类型 默认为module 支持 namespace class controller
     * @return mixed
     */
    public static function bind($bind, $type = 'module')
    {
        self::$bind = ['type' => $type, $type => $bind];
    }

    /**
     * 设置或者获取路由标识
     * @access public
     * @param string|array     $name 路由命名标识 数组表示批量设置
     * @param array            $value 路由地址及变量信息
     * @return array
     */
    public static function name($name = '', $value = null)
    {
        if (is_array($name)) {
            return self::$rules['name'] = $name;
        } elseif ('' === $name) {//mc $name不传参或者为''则表示获取$rules['name']，返回一个数组
            return self::$rules['name'];
        } elseif (!is_null($value)) {
            self::$rules['name'][strtolower($name)][] = $value;
        } else { //mc $value不传参或者为null则表示获取$rules['name']中键为$name的值
            $name = strtolower($name);
            return isset(self::$rules['name'][$name]) ? self::$rules['name'][$name] : null;
        }
    }

    /**
     * 读取路由绑定
     * @access public
     * @param string    $type 绑定类型
     * @return mixed
     */
    public static function getBind($type)
    {
        return isset(self::$bind[$type]) ? self::$bind[$type] : null;
    }

    /**
     * //mc 入口之一
     * 导入配置文件的路由规则
     * @access public
     * @param array     $rule 路由规则
     * @param string    $type 请求类型
     * @return void
     */
    public static function import(array $rule, $type = '*')
    {
        // 检查域名部署
        if (isset($rule['__domain__'])) {
            self::domain($rule['__domain__']);
            unset($rule['__domain__']);
        }

        // 检查变量规则
        if (isset($rule['__pattern__'])) {
            self::pattern($rule['__pattern__']);
            unset($rule['__pattern__']);
        }

        // 检查路由别名
        if (isset($rule['__alias__'])) {
            self::alias($rule['__alias__']);
            unset($rule['__alias__']);
        }

        // 检查资源路由
        if (isset($rule['__rest__'])) {
            self::resource($rule['__rest__']);
            unset($rule['__rest__']);
        }

        self::registerRules($rule, strtolower($type));
    }

    // 批量注册路由
    protected static function registerRules($rules, $type = '*')
    {
        foreach ($rules as $key => $val) {
            if (is_numeric($key)) {
                $key = array_shift($val);
            }
            if (empty($val)) {
                continue;
            }
            if (is_string($key) && 0 === strpos($key, '[')) {
                $key = substr($key, 1, -1);//mc 去掉[]
                self::group($key, $val);
            } elseif (is_array($val)) {
                self::setRule($key, $val[0], $type, $val[1], isset($val[2]) ? $val[2] : []);//todo method属于路由参数？
            } else {
                self::setRule($key, $val, $type);
            }
        }
    }

    /**
     * 注册路由规则
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $type 请求类型
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function rule($rule, $route = '', $type = '*', $option = [], $pattern = [])
    {
        $group = self::getGroup('name');//todo

        if (!is_null($group)) {
            // 路由分组
            $option  = array_merge(self::getGroup('option'), $option);
            $pattern = array_merge(self::getGroup('pattern'), $pattern);
        }

        $type = strtolower($type);

        if (strpos($type, '|')) {
            $option['method'] = $type;
            $type             = '*';
        }
        if (is_array($rule) && empty($route)) {
            foreach ($rule as $key => $val) {
                if (is_numeric($key)) {//todo
                    $key = array_shift($val);
                }
                if (is_array($val)) {
                    $route    = $val[0];
                    $option1  = array_merge($option, $val[1]);
                    $pattern1 = array_merge($pattern, isset($val[2]) ? $val[2] : []);
                } else {
                    $route = $val;
                }
                self::setRule($key, $route, $type, isset($option1) ? $option1 : $option, isset($pattern1) ? $pattern1 : $pattern, $group);//mc 如果在外面和规则里面同时传入了匹配参数和变量规则的话，路由规则定义里面的最终生效，对应的就是变量$option1、$pattern1，但请求类型参数以最外层决定，对应的是$type变量
            }
        } else {
            self::setRule($rule, $route, $type, $option, $pattern, $group);
        }

    }

    /**
     * 路由注册核心函数
     * 设置路由规则
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $type 请求类型
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @param string    $group 所属分组
     * @return void
     */
    /**
     * //mc note
     * $route 检测顺序：闭包-重定向-方法-控制-模块、控制器、操作
     * 方式1：路由到模块/控制器/操作    '[模块/控制器/操作]?额外参数1=值1&额外参数2=值2...'
     * 方式2：路由到重定向地址         ‘外部地址’ （默认301重定向） 或者['外部地址','重定向代码']
     * 方式3：路由到控制器的方法        @[模块/控制器/]操作
     * 方法4：路由到类的方法           '\完整的命名空间类::静态方法' 或者 '\完整的命名空间类@动态方法'
     * 方法5：路由到闭包函数           闭包函数定义（支持参数传入）
     */
    protected static function setRule($rule, $route, $type = '*', $option = [], $pattern = [], $group = '')
    {
        if (is_array($rule)) {
            $name = $rule[0];
            $rule = $rule[1];
        } elseif (is_string($route)) {
            $name = $route;
        }
        if (!isset($option['complete_match'])) {//mc complete_match为true表示开启路由定义的全局匹配
            if (Config::get('route_complete_match')) {
                $option['complete_match'] = true;
            } elseif ('$' == substr($rule, -1, 1)) {
                // 是否完整匹配
                $option['complete_match'] = true;
            }
        } elseif (empty($option['complete_match']) && '$' == substr($rule, -1, 1)) {
            // 是否完整匹配
            $option['complete_match'] = true;
        }

        if ('$' == substr($rule, -1, 1)) {
            $rule = substr($rule, 0, -1);//mc 去掉$
        }

        if ('/' != $rule || $group) {
            $rule = trim($rule, '/');
        }
        $vars = self::parseVar($rule);
        if (isset($name)) {
            $key    = $group ? $group . ($rule ? '/' . $rule : '') : $rule;
            $suffix = isset($option['ext']) ? $option['ext'] : null;
            self::name($name, [$key, $vars, self::$domain, $suffix]);
        }
        if (isset($option['modular'])) {
            $route = $option['modular'] . '/' . $route;
        }
        if ($group) {//todo
            if ('*' != $type) {
                $option['method'] = $type;
            }
            if (self::$domain) {
                self::$rules['domain'][self::$domain]['*'][$group]['rule'][] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            } else {
                self::$rules['*'][$group]['rule'][] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            }
        } else {
            if ('*' != $type && isset(self::$rules['*'][$rule])) {
                unset(self::$rules['*'][$rule]);//todo 不知道用法
            }
            if (self::$domain) {
                self::$rules['domain'][self::$domain][$type][$rule] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            } else {
                self::$rules[$type][$rule] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            }
            if ('*' == $type) {
                // 注册路由快捷方式
                foreach (['get', 'post', 'put', 'delete', 'patch', 'head', 'options'] as $method) {
                    if (self::$domain) {
                        self::$rules['domain'][self::$domain][$method][$rule] = true;
                    } else {
                        self::$rules[$method][$rule] = true;
                    }
                }
            }
        }
    }

    /**
     * 设置当前执行的参数信息
     * @access public
     * @param array    $options 参数信息
     * @return mixed
     */
    protected static function setOption($options = [])
    {
        self::$option[] = $options;
    }

    /**
     * 获取当前执行的所有参数信息
     * @access public
     * @return array
     */
    public static function getOption()
    {
        return self::$option;
    }

    /**
     * 获取当前的分组信息
     * @access public
     * @param string    $type 分组信息名称 name option pattern
     * @return mixed
     */
    public static function getGroup($type)
    {
        if (isset(self::$group[$type])) {
            return self::$group[$type];
        } else {
            return 'name' == $type ? null : [];
        }
    }

    /**
     * 设置当前的路由分组
     * @access public
     * @param string    $name 分组名称
     * @param array     $option 分组路由参数
     * @param array     $pattern 分组变量规则
     * @return void
     */
    public static function setGroup($name, $option = [], $pattern = [])
    {
        self::$group['name']    = $name;
        self::$group['option']  = $option ?: [];
        self::$group['pattern'] = $pattern ?: [];
    }

    /**
     * 注册路由分组
     * @access public
     * @param string|array      $name 分组名称或者参数
     * @param array|\Closure    $routes 路由地址
     * @param array             $option 路由参数
     * @param array             $pattern 变量规则
     * @return void
     */
    public static function group($name, $routes, $option = [], $pattern = [])
    {
        if (is_array($name)) {
            $option = $name;
            $name   = isset($option['name']) ? $option['name'] : '';
        }
        // 分组
        $currentGroup = self::getGroup('name');
        if ($currentGroup) {
            $name = $currentGroup . ($name ? '/' . ltrim($name, '/') : '');
        }
        if (!empty($name)) {
            if ($routes instanceof \Closure) {
                $currentOption  = self::getGroup('option');
                $currentPattern = self::getGroup('pattern');
                self::setGroup($name, array_merge($currentOption, $option), array_merge($currentPattern, $pattern));
                call_user_func_array($routes, []);
                self::setGroup($currentGroup, $currentOption, $currentPattern);
                if ($currentGroup != $name) {
                    self::$rules['*'][$name]['route']   = '';
                    self::$rules['*'][$name]['var']     = self::parseVar($name);
                    self::$rules['*'][$name]['option']  = $option;
                    self::$rules['*'][$name]['pattern'] = $pattern;
                }
            } else {
                $item = [];
                foreach ($routes as $key => $val) {
                    if (is_numeric($key)) {
                        $key = array_shift($val);
                    }
                    if (is_array($val)) {
                        $route    = $val[0];
                        $option1  = array_merge($option, isset($val[1]) ? $val[1] : []);
                        $pattern1 = array_merge($pattern, isset($val[2]) ? $val[2] : []);
                    } else {
                        $route = $val;
                    }

                    $options  = isset($option1) ? $option1 : $option;
                    $patterns = isset($pattern1) ? $pattern1 : $pattern;
                    if ('$' == substr($key, -1, 1)) {
                        // 是否完整匹配
                        $options['complete_match'] = true;
                        $key                       = substr($key, 0, -1);
                    }
                    $key    = trim($key, '/');
                    $vars   = self::parseVar($key);
                    $item[] = ['rule' => $key, 'route' => $route, 'var' => $vars, 'option' => $options, 'pattern' => $patterns];
                    // 设置路由标识
                    $suffix = isset($options['ext']) ? $options['ext'] : null;
                    self::name($route, [$name . ($key ? '/' . $key : ''), $vars, self::$domain, $suffix]);
                }
                self::$rules['*'][$name] = ['rule' => $item, 'route' => '', 'var' => [], 'option' => $option, 'pattern' => $pattern];
            }

            foreach (['get', 'post', 'put', 'delete', 'patch', 'head', 'options'] as $method) {
                if (!isset(self::$rules[$method][$name])) {
                    self::$rules[$method][$name] = true;
                } elseif (is_array(self::$rules[$method][$name])) {
                    self::$rules[$method][$name] = array_merge(self::$rules['*'][$name], self::$rules[$method][$name]);
                }
            }

        } elseif ($routes instanceof \Closure) {
            // 闭包注册
            $currentOption  = self::getGroup('option');
            $currentPattern = self::getGroup('pattern');
            self::setGroup('', array_merge($currentOption, $option), array_merge($currentPattern, $pattern));
            call_user_func_array($routes, []);
            self::setGroup($currentGroup, $currentOption, $currentPattern);
        } else {
            // 批量注册路由
            self::rule($routes, '', '*', $option, $pattern);
        }
    }

    /**
     * 注册路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function any($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, '*', $option, $pattern);
    }

    /**
     * 注册GET路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function get($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'GET', $option, $pattern);
    }

    /**
     * 注册POST路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function post($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'POST', $option, $pattern);
    }

    /**
     * 注册PUT路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function put($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'PUT', $option, $pattern);
    }

    /**
     * 注册DELETE路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function delete($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'DELETE', $option, $pattern);
    }

    /**
     * 注册PATCH路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function patch($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'PATCH', $option, $pattern);
    }

    /**
     * 注册资源路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    /**
     * //mc note    zilei 四期项目中的例子
     * mbm/index/welcome'=>'mall_buyer_mobile/index.welcome' 则转为 $rule='mbm/index/welcome', $route='mall_buyer_mobile/index.welcome'，转为 
     * self::rule('mbm/index/welcome$', 'mall_buyer_mobile/index.welcome/index', 'GET', [], []);
     * self::rule('mbm/index/welcome/create$', 'mall_buyer_mobile/index.welcome/create', 'GET', [], []);
     * self::rule('mbm/index/welcome/:id/edit$', 'mall_buyer_mobile/index.welcome/edit', 'GET', [], []);
     * self::rule('mbm/index/welcome/:id$', 'mall_buyer_mobile/index.welcome/read', 'GET', [], []);
     * self::rule('mbm/index/welcome$', 'mall_buyer_mobile/index.welcome/save', 'POST', [], []);
     * self::rule('mbm/index/welcome/:id$', 'mall_buyer_mobile/index.welcome/update', 'PUT', [], []);
     * self::rule('mbm/index/welcome/:id$', 'mall_buyer_mobile/index.welcome/delete', 'DELETE', [], []);
     */
    public static function resource($rule, $route = '', $option = [], $pattern = [])
    {
        if (is_array($rule)) {
            foreach ($rule as $key => $val) {
                if (is_array($val)) {
                    list($val, $option, $pattern) = array_pad($val, 3, []);//mc 以指定长度将一个值填充进数组，此处为在$val后填充 [] 到3个元素，即[$val,[],[]]
                }
                self::resource($key, $val, $option, $pattern);
            }
        } else {
            /**
             * //mc note    tp5文档上的例子
             * Route::resource('blog.comment','index/comment');
             * 就可以访问一下地址：
             * http://serverName/blog/128/comment/32
             * http://serverName/blog/128/comment/32/edit
             * 生成的路由规则分别是
             * blog/:blog_id/comment/:id
             * blog/:blog_id/comment/:id/edit
             * 对应方法：public function edit($id,$blog_id){}
             */
            if (strpos($rule, '.')) {
                // 注册嵌套资源路由
                $array = explode('.', $rule);
                $last  = array_pop($array);
                $item  = [];
                foreach ($array as $val) {
                    $item[] = $val . '/:' . (isset($option['var'][$val]) ? $option['var'][$val] : $val . '_id');
                }
                $rule = implode('/', $item) . '/' . $last;
            }
            // 注册资源路由
            foreach (self::$rest as $key => $val) {
                if ((isset($option['only']) && !in_array($key, $option['only']))
                    || (isset($option['except']) && in_array($key, $option['except']))) {
                    continue;
                }
                /**
                 * isset($last)表示$rule参数包含.
                 * strpos($val[1], ':id') 表示遍历到edit/read/update/delete 其中一个
                 * option还是有点不懂
                 */
                if (isset($last) && strpos($val[1], ':id') && isset($option['var'][$last])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$last], $val[1]);//mc 把id替换为$option['var'][$last]
                } elseif (strpos($val[1], ':id') && isset($option['var'][$rule])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$rule], $val[1]);///mc 把 id 替换为$option['var'][$rule]
                }
                $item           = ltrim($rule . $val[1], '/');
                $option['rest'] = $key;
                self::rule($item . '$', $route . '/' . $val[2], $val[0], $option, $pattern);
            }
        }
    }

    /**
     * 注册控制器路由 操作方法对应不同的请求后缀
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function controller($rule, $route = '', $option = [], $pattern = [])
    {
        foreach (self::$methodPrefix as $type => $val) {
            self::$type($rule . '/:action', $route . '/' . $val . ':action', $option, $pattern);
        }
    }

    /**
     * 注册别名路由
     * @access public
     * @param string|array  $rule 路由别名
     * @param string        $route 路由地址
     * @param array         $option 路由参数
     * @return void
     */
    public static function alias($rule = null, $route = '', $option = [])
    {
        if (is_array($rule)) {
            self::$rules['alias'] = array_merge(self::$rules['alias'], $rule);
        } else {
            self::$rules['alias'][$rule] = $option ? [$route, $option] : $route;
        }
    }

    /**
     * 设置不同请求类型下面的方法前缀
     * @access public
     * @param string    $method 请求类型
     * @param string    $prefix 类型前缀
     * @return void
     */
    public static function setMethodPrefix($method, $prefix = '')
    {
        if (is_array($method)) {
            self::$methodPrefix = array_merge(self::$methodPrefix, array_change_key_case($method));
        } else {
            self::$methodPrefix[strtolower($method)] = $prefix;
        }
    }

    /**
     * rest方法定义和修改
     * @access public
     * @param string        $name 方法名称
     * @param array|bool    $resource 资源
     * @return void
     */
    public static function rest($name, $resource = [])
    {
        if (is_array($name)) {
            self::$rest = $resource ? $name : array_merge(self::$rest, $name);
        } else {
            self::$rest[$name] = $resource;
        }
    }

    /**
     * 注册未匹配路由规则后的处理
     * @access public
     * @param string    $route 路由地址
     * @param string    $method 请求类型
     * @param array     $option 路由参数
     * @return void
     */
    public static function miss($route, $method = '*', $option = [])
    {
        self::rule('__miss__', $route, $method, $option, []);
    }

    /**
     * 注册一个自动解析的URL路由
     * @access public
     * @param string    $route 路由地址
     * @return void
     */
    public static function auto($route)
    {
        self::rule('__auto__', $route, '*', [], []);
    }

    /**
     * 获取或者批量设置路由定义
     * @access public
     * @param mixed $rules 请求类型或者路由定义数组
     * @return array
     */
    public static function rules($rules = '')
    {
        if (is_array($rules)) {
            self::$rules = $rules;
        } elseif ($rules) {
            return true === $rules ? self::$rules : self::$rules[strtolower($rules)];
        } else {
            $rules = self::$rules;
            unset($rules['pattern'], $rules['alias'], $rules['domain'], $rules['name']);
            return $rules;
        }
    }

    /**
     * 检测子域名部署
     * @access public
     * @param Request   $request Request请求对象
     * @param array     $currentRules 当前路由规则
     * @param string    $method 请求类型
     * @return void
     */
    public static function checkDomain($request, &$currentRules, $method = 'get')
    {
        // 域名规则
        $rules = self::$rules['domain'];
        // 开启子域名部署 支持二级和三级域名
        if (!empty($rules)) {
            $host = $request->host();
            if (isset($rules[$host])) {
                // 完整域名或者IP配置
                $item = $rules[$host];
            } else {
                $rootDomain = Config::get('url_domain_root');
                if ($rootDomain) {
                    // 配置域名根 例如 thinkphp.cn 163.com.cn 如果是国家级域名 com.cn net.cn 之类的域名需要配置
                    $domain = explode('.', rtrim(stristr($host, $rootDomain, true), '.'));
                } else {
                    $domain = explode('.', $host, -2);
                }
                // 子域名配置
                if (!empty($domain)) {
                    // 当前子域名
                    $subDomain       = implode('.', $domain);
                    self::$subDomain = $subDomain;
                    $domain2         = array_pop($domain);
                    if ($domain) {
                        // 存在三级域名
                        $domain3 = array_pop($domain);
                    }
                    if ($subDomain && isset($rules[$subDomain])) {
                        // 子域名配置
                        $item = $rules[$subDomain];
                    } elseif (isset($rules['*.' . $domain2]) && !empty($domain3)) {
                        // 泛三级域名
                        $item      = $rules['*.' . $domain2];
                        $panDomain = $domain3;
                    } elseif (isset($rules['*']) && !empty($domain2)) {
                        // 泛二级域名
                        if ('www' != $domain2) {
                            $item      = $rules['*'];
                            $panDomain = $domain2;
                        }
                    }
                }
            }
            if (!empty($item)) {
                if (isset($panDomain)) {
                    // 保存当前泛域名
                    $request->route(['__domain__' => $panDomain]);
                }
                if (isset($item['[bind]'])) {
                    // 解析子域名部署规则
                    list($rule, $option, $pattern) = $item['[bind]'];
                    if (!empty($option['https']) && !$request->isSsl()) {
                        // https检测
                        throw new HttpException(404, 'must use https request:' . $host);
                    }

                    if (strpos($rule, '?')) {
                        // 传入其它参数
                        $array  = parse_url($rule);
                        $result = $array['path'];
                        parse_str($array['query'], $params);
                        if (isset($panDomain)) {
                            $pos = array_search('*', $params);
                            if (false !== $pos) {
                                // 泛域名作为参数
                                $params[$pos] = $panDomain;
                            }
                        }
                        $_GET = array_merge($_GET, $params);
                    } else {
                        $result = $rule;
                    }

                    if (0 === strpos($result, '\\')) {
                        // 绑定到命名空间 例如 \app\index\behavior
                        self::$bind = ['type' => 'namespace', 'namespace' => $result];
                    } elseif (0 === strpos($result, '@')) {
                        // 绑定到类 例如 @app\index\controller\User
                        self::$bind = ['type' => 'class', 'class' => substr($result, 1)];
                    } else {
                        // 绑定到模块/控制器 例如 index/user
                        self::$bind = ['type' => 'module', 'module' => $result];
                    }
                    self::$domainBind = true;
                } else {
                    self::$domainRule = $item;
                    $currentRules     = isset($item[$method]) ? $item[$method] : $item['*'];
                }
            }
        }
    }

    /**
     * 检测URL路由
     * @access public
     * @param Request   $request Request请求对象
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @param bool      $checkDomain 是否检测域名规则
     * @return false|array
     */
    public static function check($request, $url, $depr = '/', $checkDomain = false)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace($depr, '|', $url);

        if (isset(self::$rules['alias'][$url]) || isset(self::$rules['alias'][strstr($url, '|', true)])) {
            // 检测路由别名
            $result = self::checkRouteAlias($request, $url, $depr);
            if (false !== $result) {
                return $result;
            }
        }
        $method = strtolower($request->method());
        // 获取当前请求类型的路由规则
        $rules = isset(self::$rules[$method]) ? self::$rules[$method] : [];
        // 检测域名部署
        if ($checkDomain) {
            self::checkDomain($request, $rules, $method);
        }
        // 检测URL绑定
        $return = self::checkUrlBind($url, $rules, $depr);
        if (false !== $return) {
            return $return;
        }
        if ('|' != $url) {
            $url = rtrim($url, '|');
        }
        $item = str_replace('|', '/', $url);//mc 又将分隔符切换为/，然后赋值给item
        if (isset($rules[$item])) {
            // 静态路由规则检测
            $rule = $rules[$item];
            if (true === $rule) {
                $rule = self::getRouteExpress($item);
            }
            if (!empty($rule['route']) && self::checkOption($rule['option'], $request)) {
                self::setOption($rule['option']);
                return self::parseRule($item, $rule['route'], $url, $rule['option']);
            }
        }

        // 路由规则检测
        if (!empty($rules)) {
            return self::checkRoute($request, $rules, $url, $depr);
        }
        return false;
    }

    private static function getRouteExpress($key)
    {
        return self::$domainRule ? self::$domainRule['*'][$key] : self::$rules['*'][$key];
    }

    /**
     * 检测路由规则
     * @access private
     * @param Request   $request
     * @param array     $rules 路由规则
     * @param string    $url URL地址
     * @param string    $depr URL分割符
     * @param string    $group 路由分组名
     * @param array     $options 路由参数（分组）
     * @return mixed
     */
    private static function checkRoute($request, $rules, $url, $depr = '/', $group = '', $options = [])
    {
        foreach ($rules as $key => $item) {
            if (true === $item) {
                $item = self::getRouteExpress($key);
            }
            if (!isset($item['rule'])) {
                continue;
            }
            $rule    = $item['rule'];
            $route   = $item['route'];
            $vars    = $item['var'];
            $option  = $item['option'];
            $pattern = $item['pattern'];

            // 检查参数有效性
            if (!self::checkOption($option, $request)) {
                continue;
            }

            if (isset($option['ext'])) {
                // 路由ext参数 优先于系统配置的URL伪静态后缀参数
                $url = preg_replace('/\.' . $request->ext() . '$/i', '', $url);
            }

            if (is_array($rule)) {
                // 分组路由
                $pos = strpos(str_replace('<', ':', $key), ':');
                if (false !== $pos) {
                    $str = substr($key, 0, $pos);
                } else {
                    $str = $key;
                }
                if (is_string($str) && $str && 0 !== stripos(str_replace('|', '/', $url), $str)) {
                    continue;
                }
                self::setOption($option);
                $result = self::checkRoute($request, $rule, $url, $depr, $key, $option);
                if (false !== $result) {
                    return $result;
                }
            } elseif ($route) {
                if ('__miss__' == $rule || '__auto__' == $rule) {
                    // 指定特殊路由
                    $var    = trim($rule, '__');
                    ${$var} = $item;
                    continue;
                }
                if ($group) {
                    $rule = $group . ($rule ? '/' . ltrim($rule, '/') : '');
                }

                self::setOption($option);
                if (isset($options['bind_model']) && isset($option['bind_model'])) {
                    $option['bind_model'] = array_merge($options['bind_model'], $option['bind_model']);
                }
                $result = self::checkRule($rule, $route, $url, $pattern, $option, $depr);
                if (false !== $result) {
                    return $result;
                }
            }
        }
        if (isset($auto)) {
            // 自动解析URL地址
            return self::parseUrl($auto['route'] . '/' . $url, $depr);
        } elseif (isset($miss)) {
            // 未匹配所有路由的路由规则处理
            return self::parseRule('', $miss['route'], $url, $miss['option']);
        }
        return false;
    }

    /**
     * 检测路由别名
     * @access private
     * @param Request   $request
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @return mixed
     */
    private static function checkRouteAlias($request, $url, $depr)
    {
        $array = explode('|', $url);
        $alias = array_shift($array);
        $item  = self::$rules['alias'][$alias];

        if (is_array($item)) {
            list($rule, $option) = $item;
            $action              = $array[0];
            if (isset($option['allow']) && !in_array($action, explode(',', $option['allow']))) {
                // 允许操作
                return false;
            } elseif (isset($option['except']) && in_array($action, explode(',', $option['except']))) {
                // 排除操作
                return false;
            }
            if (isset($option['method'][$action])) {
                $option['method'] = $option['method'][$action];
            }
        } else {
            $rule = $item;
        }
        $bind = implode('|', $array);
        // 参数有效性检查
        if (isset($option) && !self::checkOption($option, $request)) {
            // 路由不匹配
            return false;
        } elseif (0 === strpos($rule, '\\')) {
            // 路由到类
            return self::bindToClass($bind, substr($rule, 1), $depr);
        } elseif (0 === strpos($rule, '@')) {
            // 路由到控制器类
            return self::bindToController($bind, substr($rule, 1), $depr);
        } else {
            // 路由到模块/控制器
            return self::bindToModule($bind, $rule, $depr);
        }
    }

    /**
     * 检测URL绑定
     * @access private
     * @param string    $url URL地址
     * @param array     $rules 路由规则
     * @param string    $depr URL分隔符
     * @return mixed
     */
    private static function checkUrlBind(&$url, &$rules, $depr = '/')
    {
        if (!empty(self::$bind)) {
            $type = self::$bind['type'];
            $bind = self::$bind[$type];
            // 记录绑定信息
            App::$debug && Log::record('[ BIND ] ' . var_export($bind, true), 'info');
            // 如果有URL绑定 则进行绑定检测
            switch ($type) {
                case 'class':
                    // 绑定到类
                    return self::bindToClass($url, $bind, $depr);
                case 'controller':
                    // 绑定到控制器类
                    return self::bindToController($url, $bind, $depr);
                case 'namespace':
                    // 绑定到命名空间
                    return self::bindToNamespace($url, $bind, $depr);
            }
        }
        return false;
    }

    /**
     * 绑定到类
     * @access public
     * @param string    $url URL地址
     * @param string    $class 类名（带命名空间）
     * @param string    $depr URL分隔符
     * @return array
     */
    public static function bindToClass($url, $class, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'method', 'method' => [$class, $action], 'var' => []];
    }

    /**
     * 绑定到命名空间
     * @access public
     * @param string    $url URL地址
     * @param string    $namespace 命名空间
     * @param string    $depr URL分隔符
     * @return array
     */
    public static function bindToNamespace($url, $namespace, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : Config::get('default_controller');
        $method = !empty($array[1]) ? $array[1] : Config::get('default_action');
        if (!empty($array[2])) {
            self::parseUrlParams($array[2]);
        }
        return ['type' => 'method', 'method' => [$namespace . '\\' . Loader::parseName($class, 1), $method], 'var' => []];
    }

    /**
     * 绑定到控制器类
     * @access public
     * @param string    $url URL地址
     * @param string    $controller 控制器名 （支持带模块名 index/user ）
     * @param string    $depr URL分隔符
     * @return array
     */
    public static function bindToController($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'controller', 'controller' => $controller . '/' . $action, 'var' => []];
    }

    /**
     * 绑定到模块/控制器
     * @access public
     * @param string    $url URL地址
     * @param string    $controller 控制器类名（带命名空间）
     * @param string    $depr URL分隔符
     * @return array
     */
    public static function bindToModule($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'module', 'module' => $controller . '/' . $action];
    }

    /**
     * 路由参数有效性检查
     * @access private
     * @param array     $option 路由参数
     * @param Request   $request Request对象
     * @return bool
     */
    private static function checkOption($option, $request)
    {
        if ((isset($option['method']) && is_string($option['method']) && false === stripos($option['method'], $request->method()))
            || (isset($option['ajax']) && $option['ajax'] && !$request->isAjax()) // Ajax检测
             || (isset($option['ajax']) && !$option['ajax'] && $request->isAjax()) // 非Ajax检测
             || (isset($option['pjax']) && $option['pjax'] && !$request->isPjax()) // Pjax检测
             || (isset($option['pjax']) && !$option['pjax'] && $request->isPjax()) // 非Pjax检测
             || (isset($option['ext']) && false === stripos('|' . $option['ext'] . '|', '|' . $request->ext() . '|')) // 伪静态后缀检测
             || (isset($option['deny_ext']) && false !== stripos('|' . $option['deny_ext'] . '|', '|' . $request->ext() . '|'))
            || (isset($option['domain']) && !in_array($option['domain'], [$_SERVER['HTTP_HOST'], self::$subDomain])) // 域名检测
             || (isset($option['https']) && $option['https'] && !$request->isSsl()) // https检测
             || (isset($option['https']) && !$option['https'] && $request->isSsl()) // https检测
             || (!empty($option['before_behavior']) && false === Hook::exec($option['before_behavior'])) // 行为检测
             || (!empty($option['callback']) && is_callable($option['callback']) && false === call_user_func($option['callback'])) // 自定义检测
        ) {
            return false;
        }
        return true;
    }

    /**
     * 检测路由规则
     * @access private
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $url URL地址
     * @param array     $pattern 变量规则
     * @param array     $option 路由参数
     * @param string    $depr URL分隔符（全局）
     * @return array|false
     */
    private static function checkRule($rule, $route, $url, $pattern, $option, $depr)
    {
        // 检查完整规则定义
        if (isset($pattern['__url__']) && !preg_match(0 === strpos($pattern['__url__'], '/') ? $pattern['__url__'] : '/^' . $pattern['__url__'] . '/', str_replace('|', $depr, $url))) {
            return false;
        }
        // 检查路由的参数分隔符
        if (isset($option['param_depr'])) {
            $url = str_replace(['|', $option['param_depr']], [$depr, '|'], $url);
        }

        $len1 = substr_count($url, '|');
        $len2 = substr_count($rule, '/');
        // 多余参数是否合并
        $merge = !empty($option['merge_extra_vars']);
        if ($merge && $len1 > $len2) {
            $url = str_replace('|', $depr, $url);
            $url = implode('|', explode($depr, $url, $len2 + 1));
        }

        if ($len1 >= $len2 || strpos($rule, '[')) {
            if (!empty($option['complete_match'])) {
                // 完整匹配
                if (!$merge && $len1 != $len2 && (false === strpos($rule, '[') || $len1 > $len2 || $len1 < $len2 - substr_count($rule, '['))) {
                    return false;
                }
            }
            $pattern = array_merge(self::$rules['pattern'], $pattern);
            if (false !== $match = self::match($url, $rule, $pattern)) {
                // 匹配到路由规则
                return self::parseRule($rule, $route, $url, $option, $match);
            }
        }
        return false;
    }

    /**
     * 解析模块的URL地址 [模块/控制器/操作?]参数1=值1&参数2=值2...
     * @access public
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @param bool      $autoSearch 是否自动深度搜索控制器
     * @return array
     */
    public static function parseUrl($url, $depr = '/', $autoSearch = false)
    {

        if (isset(self::$bind['module'])) {
            $bind = str_replace('/', $depr, self::$bind['module']);
            // 如果有模块/控制器绑定
            $url = $bind . ('.' != substr($bind, -1) ? $depr : '') . ltrim($url, $depr);
        }
        $url              = str_replace($depr, '|', $url);
        list($path, $var) = self::parseUrlPath($url);
        $route            = [null, null, null];
        if (isset($path)) {
            // 解析模块
            $module = Config::get('app_multi_module') ? array_shift($path) : null;
            if ($autoSearch) {
                // 自动搜索控制器
                $dir    = APP_PATH . ($module ? $module . DS : '') . Config::get('url_controller_layer');
                $suffix = App::$suffix || Config::get('controller_suffix') ? ucfirst(Config::get('url_controller_layer')) : '';
                $item   = [];
                $find   = false;
                foreach ($path as $val) {
                    $item[] = $val;
                    $file   = $dir . DS . str_replace('.', DS, $val) . $suffix . EXT;
                    $file   = pathinfo($file, PATHINFO_DIRNAME) . DS . Loader::parseName(pathinfo($file, PATHINFO_FILENAME), 1) . EXT;
                    if (is_file($file)) {
                        $find = true;
                        break;
                    } else {
                        $dir .= DS . Loader::parseName($val);
                    }
                }
                if ($find) {
                    $controller = implode('.', $item);
                    $path       = array_slice($path, count($item));
                } else {
                    $controller = array_shift($path);
                }
            } else {
                // 解析控制器
                $controller = !empty($path) ? array_shift($path) : null;
            }
            // 解析操作
            $action = !empty($path) ? array_shift($path) : null;
            // 解析额外参数
            self::parseUrlParams(empty($path) ? '' : implode('|', $path));
            // 封装路由
            $route = [$module, $controller, $action];
            // 检查地址是否被定义过路由
            $name  = strtolower($module . '/' . Loader::parseName($controller, 1) . '/' . $action);
            $name2 = '';
            if (empty($module) || isset($bind) && $module == $bind) {
                $name2 = strtolower(Loader::parseName($controller, 1) . '/' . $action);
            }

            if (isset(self::$rules['name'][$name]) || isset(self::$rules['name'][$name2])) {
                throw new HttpException(404, 'invalid request:' . str_replace('|', $depr, $url));
            }
        }
        return ['type' => 'module', 'module' => $route];
    }

    /**
     * 解析URL的pathinfo参数和变量
     * @access private
     * @param string    $url URL地址
     * @return array
     */
    private static function parseUrlPath($url)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace('|', '/', $url);
        $url = trim($url, '/');
        $var = [];
        if (false !== strpos($url, '?')) {
            // [模块/控制器/操作?]参数1=值1&参数2=值2...
            $info = parse_url($url);
            $path = explode('/', $info['path']);
            parse_str($info['query'], $var);
        } elseif (strpos($url, '/')) {
            // [模块/控制器/操作]
            $path = explode('/', $url);
        } else {
            $path = [$url];
        }
        return [$path, $var];
    }

    /**
     * 检测URL和规则路由是否匹配
     * @access private
     * @param string    $url URL地址
     * @param string    $rule 路由规则
     * @param array     $pattern 变量规则
     * @return array|false
     */
    private static function match($url, $rule, $pattern)
    {
        $m2 = explode('/', $rule);
        $m1 = explode('|', $url);

        $var = [];
        foreach ($m2 as $key => $val) {
            // val中定义了多个变量 <id><name>
            if (false !== strpos($val, '<') && preg_match_all('/<(\w+(\??))>/', $val, $matches)) {
                $value   = [];
                $replace = [];
                foreach ($matches[1] as $name) {
                    if (strpos($name, '?')) {
                        $name      = substr($name, 0, -1);
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . ')?';
                    } else {
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . ')';
                    }
                    $value[] = $name;
                }
                $val = str_replace($matches[0], $replace, $val);
                if (preg_match('/^' . $val . '$/', isset($m1[$key]) ? $m1[$key] : '', $match)) {
                    array_shift($match);
                    foreach ($value as $k => $name) {
                        if (isset($match[$k])) {
                            $var[$name] = $match[$k];
                        }
                    }
                    continue;
                } else {
                    return false;
                }
            }

            if (0 === strpos($val, '[:')) {
                // 可选参数
                $val      = substr($val, 1, -1);
                $optional = true;
            } else {
                $optional = false;
            }
            if (0 === strpos($val, ':')) {
                // URL变量
                $name = substr($val, 1);
                if (!$optional && !isset($m1[$key])) {
                    return false;
                }
                if (isset($m1[$key]) && isset($pattern[$name])) {
                    // 检查变量规则
                    if ($pattern[$name] instanceof \Closure) {
                        $result = call_user_func_array($pattern[$name], [$m1[$key]]);
                        if (false === $result) {
                            return false;
                        }
                    } elseif (!preg_match(0 === strpos($pattern[$name], '/') ? $pattern[$name] : '/^' . $pattern[$name] . '$/', $m1[$key])) {
                        return false;
                    }
                }
                $var[$name] = isset($m1[$key]) ? $m1[$key] : '';
            } elseif (!isset($m1[$key]) || 0 !== strcasecmp($val, $m1[$key])) {
                return false;
            }
        }
        // 成功匹配后返回URL中的动态变量数组
        return $var;
    }

    /**
     * 解析规则路由
     * @access private
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $pathinfo URL地址
     * @param array     $option 路由参数
     * @param array     $matches 匹配的变量
     * @return array
     */
    private static function parseRule($rule, $route, $pathinfo, $option = [], $matches = [])
    {
        $request = Request::instance();
        // 解析路由规则
        if ($rule) {
            $rule = explode('/', $rule);//mc array explode ( string $delimiter , string $string [, int $limit ] ) 如果设置了 limit 参数并且是正数，则返回的数组包含最多 limit 个元素，而最后那个元素将包含 string 的剩余部分，即此处的合并额外变量
            // 获取URL地址中的参数
            $paths = explode('|', $pathinfo);
            foreach ($rule as $item) {
                $fun = '';
                if (0 === strpos($item, '[:')) {
                    $item = substr($item, 1, -1);//mc 去掉[]
                }
                if (0 === strpos($item, ':')) {
                    $var           = substr($item, 1);//mc 去掉:
                    $matches[$var] = array_shift($paths);//mc 将 array 的第一个单元移出并作为结果返回，array_shift会将除额外参数外的都移出数组，变量的则放到$matches数组中，$var为键/变量名，$paths对应位置参数为值
                } else {
                    // 过滤URL中的静态变量
                    array_shift($paths);
                }
            }
        } else {
            $paths = explode('|', $pathinfo);
        }

        // 获取路由地址规则
        if (is_string($route) && isset($option['prefix'])) {
            // 路由地址前缀
            $route = $option['prefix'] . $route;
        }
        // 替换路由地址中的变量
        if (is_string($route) && !empty($matches)) {
            foreach ($matches as $key => $val) {
                if (false !== strpos($route, ':' . $key)) {//mc //动态路由，eg：':action/blog/:id' => 'index/blog/:action' 此处有效的是前后对应的:action
                    $route = str_replace(':' . $key, $val, $route);
                }
            }
        }

        // 绑定模型数据
        if (isset($option['bind_model'])) {//todo
            $bind = [];
            foreach ($option['bind_model'] as $key => $val) {
                if ($val instanceof \Closure) {
                    $result = call_user_func_array($val, [$matches]);
                } else {
                    if (is_array($val)) {
                        $fields    = explode('&', $val[1]);
                        $model     = $val[0];
                        $exception = isset($val[2]) ? $val[2] : true;
                    } else {
                        $fields    = ['id'];
                        $model     = $val;
                        $exception = true;
                    }
                    $where = [];
                    $match = true;
                    foreach ($fields as $field) {
                        if (!isset($matches[$field])) {
                            $match = false;
                            break;
                        } else {
                            $where[$field] = $matches[$field];
                        }
                    }
                    if ($match) {
                        $query  = strpos($model, '\\') ? $model::where($where) : Loader::model($model)->where($where);
                        $result = $query->failException($exception)->find();
                    }
                }
                if (!empty($result)) {
                    $bind[$key] = $result;
                }
            }
            $request->bind($bind);
        }

        if (!empty($option['response'])) {
            Hook::add('response_send', $option['response']);
        }

        // 解析额外参数
        self::parseUrlParams(empty($paths) ? '' : implode('|', $paths), $matches);
        // 记录匹配的路由信息
        $request->routeInfo(['rule' => $rule, 'route' => $route, 'option' => $option, 'var' => $matches]);

        // 检测路由after行为
        if (!empty($option['after_behavior'])) {
            if ($option['after_behavior'] instanceof \Closure) {
                $result = call_user_func_array($option['after_behavior'], []);
            } else {
                foreach ((array) $option['after_behavior'] as $behavior) {
                    $result = Hook::exec($behavior, '');
                    if (!is_null($result)) {
                        break;
                    }
                }
            }
            // 路由规则重定向
            if ($result instanceof Response) {
                return ['type' => 'response', 'response' => $result];
            } elseif (is_array($result)) {
                return $result;
            }
        }

        if ($route instanceof \Closure) {
            // 执行闭包
            $result = ['type' => 'function', 'function' => $route];
        } elseif (0 === strpos($route, '/') || strpos($route, '://')) {
            // 路由到重定向地址
            $result = ['type' => 'redirect', 'url' => $route, 'status' => isset($option['status']) ? $option['status'] : 301];
        } elseif (false !== strpos($route, '\\')) {
            // 路由到方法
            list($path, $var) = self::parseUrlPath($route);
            $route            = str_replace('/', '@', implode('/', $path));
            $method           = strpos($route, '@') ? explode('@', $route) : $route;
            $result           = ['type' => 'method', 'method' => $method, 'var' => $var];
        } elseif (0 === strpos($route, '@')) {
            // 路由到控制器
            //mc 直接执行某个控制器类的方法，而不需要去解析 模块/控制器/操作这些，同时也不会去初始化模块。相当于直接执行Loader::action($result);
            $route             = substr($route, 1);
            list($route, $var) = self::parseUrlPath($route);
            $result            = ['type' => 'controller', 'controller' => implode('/', $route), 'var' => $var];
            $request->action(array_pop($route));
            $request->controller($route ? array_pop($route) : Config::get('default_controller'));
            $request->module($route ? array_pop($route) : Config::get('default_module'));
            App::$modulePath = APP_PATH . (Config::get('app_multi_module') ? $request->module() . DS : '');
        } else {
            // 路由到模块/控制器/操作
            $result = self::parseModule($route);
        }
        // 开启请求缓存
        if ($request->isGet() && isset($option['cache'])) {
            $cache = $option['cache'];
            if (is_array($cache)) {
                list($key, $expire) = $cache;
            } else {
                $key    = str_replace('|', '/', $pathinfo);
                $expire = $cache;
            }
            $request->cache($key, $expire);
        }
        return $result;
    }

    /**
     * 解析URL地址为 模块/控制器/操作
     * @access private
     * @param string    $url URL地址
     * @return array
     */
    private static function parseModule($url)
    {
        list($path, $var) = self::parseUrlPath($url);
        $action           = array_pop($path);
        $controller       = !empty($path) ? array_pop($path) : null;
        $module           = Config::get('app_multi_module') && !empty($path) ? array_pop($path) : null;
        $method           = Request::instance()->method();
        if (Config::get('use_action_prefix') && !empty(self::$methodPrefix[$method])) {
            // 操作方法前缀支持
            $action = 0 !== strpos($action, self::$methodPrefix[$method]) ? self::$methodPrefix[$method] . $action : $action;
        }
        // 设置当前请求的路由变量
        Request::instance()->route($var);
        // 路由到模块/控制器/操作
        return ['type' => 'module', 'module' => [$module, $controller, $action], 'convert' => false];
    }

    /**
     * 解析URL地址中的参数Request对象
     * @access private
     * @param string    $rule 路由规则
     * @param array     $var 变量
     * @return void
     */
    private static function parseUrlParams($url, &$var = [])
    {
        if ($url) {
            if (Config::get('url_param_type')) {//mc URL参数方式 0 按名称成对解析 1 按顺序解析
                $var += explode('|', $url);
            } else {
                preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                    $var[$match[1]] = strip_tags($match[2]);
                }, $url);
            }
        }
        // 设置当前请求的参数
        Request::instance()->route($var);
    }

    // 分析路由规则中的变量
    private static function parseVar($rule)
    {
        // 提取路由规则中的变量
        $var = [];
        foreach (explode('/', $rule) as $val) {
            $optional = false;
            /**
             * //mc note
             * 组合变量的优势是，路由规则中没有固定的分隔符，可以随意组合需要的变量规则，此时用到 <(\w+(\??))> 
             * int preg_match_all ( string $pattern , string $subject [, array &$matches [, int $flags = PREG_PATTERN_ORDER [, int $offset = 0 ]]] )
             * matches 为二维数组
             * 此处 $flags 为默认的 PREG_PATTERN_ORDER ，即匹配结果 $matches[0] 为包含标签的匹配项，$matches[1] 为第一个匹配项 (\w+(\??)) ，即标签内容，$matches[2] 为第二个匹配项(\??)，对应每个匹配项后是否有问号
             */
            if (false !== strpos($val, '<') && preg_match_all('/<(\w+(\??))>/', $val, $matches)) {
                foreach ($matches[1] as $name) {
                    if (strpos($name, '?')) {//mc 表示可选变量
                        $name     = substr($name, 0, -1);//mc 去掉问号
                        $optional = true;
                    } else {
                        $optional = false;
                    }
                    $var[$name] = $optional ? 2 : 1;
                }
            }

            if (0 === strpos($val, '[:')) {
                // 可选参数
                $optional = true;
                $val      = substr($val, 1, -1);//mc 去掉[]
            }
            if (0 === strpos($val, ':')) {
                // URL变量
                $name       = substr($val, 1);//mc 去掉:
                $var[$name] = $optional ? 2 : 1;
            }
        }
        return $var;
    }
}
