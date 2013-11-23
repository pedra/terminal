<?php
/* This script is based in http://sourceforge.net/projects/phpterm/
 * 
 * TODO
 * 1 - interface User Manager (create/delete/edit)
 * 2 - message board (social network - leave a message to other user.)
 * 3 - change commands: GET to POST
 * 
 * contact: Bill Rocha - prbr@ymail.com | Tel.: +55 21 98795 0673 (Rio de Janeiro, Brazil) 
 * Terminal version 0.1 - 2013/11/20-14:18:00
 */

//initial database creator
if(!file_exists('sync.db')) createDbUsers();

session_start();

if (!isset($_SERVER['REQUEST_SCHEME'])) $_SERVER['REQUEST_SCHEME'] = 'http';
define('URL', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/sync_new/');

//startup User 
$user = new User((new DB)->getUsers());
if(isset($_GET['logout'])) $user->logout();
$user->login();


// ------------------------------------------> CONTROLLERS
$screen = '';
$command = '';

  if(!$user->getVal('dir')) $user->setVal('dir', __DIR__);

  //TERMINAL COMMANDS <------------------------------------
  if(isset($_GET['command'])) {
      
    //get linux prompt
    $sh = shell_exec("whoami");
    $host = explode(".", shell_exec("uname -n"));
    $screen = $user->getVal('screen')."<span class=\"command\">".rtrim($sh).""."@"."".rtrim($host[0]).':';
    
    $command = trim($_GET['command']);
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') $temp = 'cd '.$user->getVal('dir').' &'.$command.' & cd';
    else $temp = 'cd '.$user->getVal('dir').' ;'.$command.' ; pwd'; 
    
    $term = shell_exec($temp);

    $x = explode("\n", $term);
    $user->setVal('dir', $x[count($x)-2]);

    array_pop($x);
    array_pop($x);

    $screen .= $user->getVal('dir').' # '.$command."</span>\n\n".htmlentities(implode("\n", $x))."\n\n";

    $user->setVal('command', $command);
  } else $screen .= $user->getVal('dir')." # \n";
  
  //USER COMMANDS <------------------------------------------
  if(isset($_GET['user'])){
      if($_GET['user'] == 'list') $screen .= "</span>\n\n".p((new DB)->getUsers())."\n\n";
      if($_GET['user'] == 'clear') $screen = '';
  }
  //preserv terminal
  $user->setVal('screen', $screen);
  
  //load HTML -> terminal
  view('terminal', array('screen'=>$screen, 'command'=>$command));
  
  
// ------------------------------------------> OBJECTS
class DB extends SQLite3 {

    private $database = 'sync.db';

    function __construct($database = null) {
        if ($database != null) $this->database = $database;
        $this->open($this->database);
    }

    function createTable() {
        return $this->exec('CREATE TABLE USERS(LOGIN TEXT PRIMARY KEY, 
                                               PASSWORD TEXT,
                                               NAME TEXT, 
                                               ABOUT TEXT, 
                                               CONTACT TEXT, 
                                               LEVEL INTEGER, 
                                               TOKEN CHAR(33));');
    }

    function newUser($login, $password, $name, $about='', $contact='', $level = 1, $token = null) {
        if ($token == null) $token = time();
        $token = md5($token);

        //User exists?	
        if ($this->getUser($login)) return false;

        //insert new user
        if ($this->exec('INSERT INTO USERS (LOGIN, PASSWORD, NAME, ABOUT, CONTACT, LEVEL, TOKEN) 
                                    VALUES ("'.trim($login).
                                        '", "'.md5($password).
                                        '", "'.trim($name).
                                        '", "'.trim($about).
                                        '", "'.trim($contact).
                                        '", '.(0 + $level).', "'.$token.'")')) return $token;
        else return false;
    }

    function getUsers() {
        $ret = $this->query('SELECT * FROM USERS');
        $a = false;
        while ($row = $ret->fetchArray(SQLITE3_ASSOC)) {
            $a[$row['LOGIN']] = $row;
        }
        return $a;
    }

    function getUser($login) {
        $ret = $this->query('SELECT * FROM USERS WHERE LOGIN = "' . $login . '"');
        $a = false;
        while ($row = $ret->fetchArray(SQLITE3_ASSOC)) {
            $a[$row['LOGIN']] = $row;
        }
        return $a;
    }

    function __destroy() {
        $this->close();
    }
}

class User {
    
    private $data = array(); //user data from DB
    private $id = false;

    //Builder the User
    function __construct($data) {
        $this->data = $data;
        $this->id = key($data);
    }
    
    //Check status
    function logged() {
        return $this->id;
    }

    //Set user session value
    function setVal($name, $value) {
        return $_SESSION[$this->id][$name] = $value;
    }

    //Get user session value
    function getVal($name) {
        return (isset($_SESSION[$this->id][$name]) ? $_SESSION[$this->id][$name] : false);
    }
    
    //login
    function login(){
        if(isset($_SESSION['login'])) return true;
        $msg = '';
        if(isset($_POST['login']) && isset($_POST['password'])){
            $login = trim($_POST['login']);
            $password = trim($_POST['password']);
            
            $db = new DB();
            $a = $db->getUser($login);
            if($a && $a[$login]['PASSWORD'] == md5($password)){
                $this->data = array($login=>$a);
                $_SESSION['login'] = $login;
                $_SESSION[$login] = array();
                return $this->id = $login;               
            }
            $msg = 'Please, enter your login and password';
        }
        view('login', array('msg'=>$msg));
        exit();
    }

    //Destroy the User!
    static function logout() {        
        @session_destroy();//destroy session
        unset($_SESSION);
    }
}
  
// ------------------------------------------> FUNCTIONS
function p($val, $x = false) {
    $o = '<pre>' . print_r($val, true) . '</pre>';
    return (!$x) ? $o : exit($o);
}

function createDbUsers(){
    $db = new DB;
    $db->createTable();
    echo '<h1>Terminal</h1><p><b>Creating SQLite database and basic users!</b></p>';
    echo '<br>insert: Admin - token: ' . $ret = $db->newUser('admin', 'xfg234A#admin','Bill Rocha', 'Author & Administrator','prbr@ymail.com', 10);
    echo '<br>insert: User  - token: ' . $ret = $db->newUser('user', 'xfg234A#user', 'User Default', 'Other User', 'Tel.: +55 21 9 8795 0673', 2);
    echo '<br>insert: Guest - token: ' . $ret = $db->newUser('guest', 'xfg234A#guest', 'I\'m a Guest User');

    $x = $db->getUsers();
    p($x, true);
}

// ------------------------------------------> HTML VIEWS 
function view($name, $values = array()){
    extract($values);
?>
<!doctype html>
<html>
    <head>
        <title>Terminal</title>
        <meta charset="ut-8"/>
        <style>
            * {padding:0; margin:0;font-family: Arial, Verdana, Tahoma, sans-serif; font-size: 11px; transition: .6s; -webkit-transition: .6s;}
            *::-moz-placeholder {color:#357}
            *::-webkit-input-placeholder {color:#357}
            *:-ms-input-placeholder {color:#357}
            body {background: #333 url(bb.jpg) fixed; background-size: cover; min-width: 450px;}
            h1 { font-size: 26px; color: #494}
            label { font-weight: normal; color:#999; }
            button { padding: 5px 20px; margin-top: 20px}

            .topbar {position: fixed; left:0; right: 0; top:0; background: #68A; color: #FFF; min-width: 450px;}
            .topbar #menu { float: left; padding: 8px 20px !important; border-right: 1px solid #579; cursor: pointer; z-index: 200}
            .topbar #menu:hover { background: #686;}
            .topbar #menu:hover ul.submenu{display:block}
            .topbar #submenu.show {display:block}
            .topbar #command {float:left; min-width: 330px; padding: 8px; background: transparent; color:#FFF; 
                              border: none; border-left:1px solid #79B;}
            
            ul.submenu {display:none; position: fixed; left:0; top:27px; min-width: 200px; padding: 0 0 0 0; background: #686; color:#FFF; 
                        box-shadow: 0 10px 20px #000; z-index: 199}
            ul.submenu li {list-style: none; }
            ul.submenu li a{ display:block; padding:15px 10px 15px 20px; margin-top:0px; color:#FFF; text-decoration: none; 
                                border-top:1px solid #797; border-bottom: 1px solid #575;}
            ul.submenu li:first-child a{border-top:1px solid #686; padding-top: 15px}
            ul.submenu li a:hover {background: #474; border-top:1px solid #686;}                       
            
            .screen { color: #4F9; padding:10px; margin: 25px 0 0 0; white-space: pre-wrap; font-size:11px;}
            .screen, .screen * {font-family: 'Lucida Sans Typewriter', 'Lucida console', 'Courier New', Monospace, Tahoma, monospaced;}
            .screen span.command {color:#FF0}
            
            .login {position: absolute; left:50%; top:40%; margin:-100px 0 0 -124px; width: 200px; 
                    border: 1px solid #DDD; box-shadow: 0 6px 20px #000;
                    background:#FFF; padding:20px;
                    transform:scale(0.9,.9); -webkit-transform:scale(.9);}
            .login:hover{-webkit-transform:scale(1); transform:scale(1,1);}            
            .login form *{display:block}
            .login input {margin: 3px 0 10px 0; width: 185px; color:#000; font-weight: bold; font-size: 14px; border:1px solid #DDD; padding: 5px;}
            .login .msg {color:#F60; margin: 0 0 20px 0; font-style: italic}
        </style>
        <script>
            window.onload = function(){setTimeout(function(){window.scrollTo(0, document.body.scrollHeight)},100)}
            function toggleMenu() {
                document.getElementById("submenu").classList.toggle("show");
            }
        </script>
    </head>
    <body>
<?php if($name == 'terminal'){ ?>
        <div class="topbar">
            <div id="menu" onclick="toggleMenu();">Menu
                <ul id="submenu" class="submenu">
                    <li><a href="<?php echo URL;?>terminal.php?command=ls -ls">List current directory</a></li>
                    <li><a href="<?php echo URL;?>terminal.php?command=cd /var/www">Goto '/var/www'</a></li>
                    <li><a href="<?php echo URL;?>terminal.php?user=list">List Users</a></li>
                    <li><a href="<?php echo URL;?>terminal.php?user=clear">Clear terminal</a></li>
                    <li><a href="<?php echo URL;?>terminal.php?logout=true">Logout</a></li>
                </ul>
            </div>		
            <form method="get" action="<?php echo URL;?>terminal.php">
                <input type="text" id="command" name="command" placeholder="type a command here..." value=""/>
            </form>
        </div>        
        <div class="screen" id="screen"><?= $screen ?></div>
    </body>
</html>
<?php }
    if($name == 'login'){
        if(!isset($msg)) $msg = '';
        ?>
        <div class="login">            
            <form method="post" action="<?php echo URL;?>terminal.php">
                <h1>Authentication</h1>
                <span class="msg"><?=$msg?></span>	
                <label>Login:</label>
                <input type="text" name="login" />
                <label>PassWord:</label>
                <input type="password" name="password" />
                <button>Login</button>
            </form>            
        </div>
    </body>
</html>
<?php
    }
}
