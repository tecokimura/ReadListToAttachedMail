<?php
/**
 * Created by PhpStorm.
 * User: ace
 * Date: 2016/09/01
 * Time: 01:24
 *
 * php rltam.php ${CONF_FNAME} ${SMTP_SERVER} ${SMTP_PORTNO}
 * php rltam-auto.php ${CONF_FNAME} ${SMTP_SERVER} ${SMTP_PORTNO}
 * php rltam-dry.php ${CONF_FNAME} ${SMTP_SERVER} ${SMTP_PORTNO}
 *  とファイル名を変えることで動作モードを変更できる
 *
 * UTF8のPHPがSJISのファイルを読む
 * 1行目はWindowsファイルパスなのでSJISのまま使う
 * 2行目以降は設定値なのでUTF-8にして使用する
 */


require_once './vendor/autoload.php';
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


// 実行時のオプション設定
define('ARGV_INDEX_PHP_FNAME', 0);
define('ARGV_INDEX_CONF_FNAME', 1);
define('ARGV_INDEX_SMTP_SERVER', 2);
define('ARGV_INDEX_SMTP_PORT_NO', 3);
define('ARGV_INDEX_MAX', 4);

define('MAIL_FROM', 'noreply@tecotec.co.jp');

define('OS_ENC', 'SJIS');

define('LOG_LEVEL', Logger::NOTICE);



// 起動オプション確認、第一引数から設定ファイル名を取得する
// 設定ファイル：一行目：PDFパス。2行目：name,mailaddress
main($argc, $argv);
exit;


/**
 * プログラムのメイン処理
 * 設定ファイルから必要なファイルを添付してメールを送信する
 *
 * @param $argc
 * @param $argv
 */
function main($argc, $argv)
{
    try {
        
        // 使い方の表示をするかどうか
        $isViewHelp = false;
        
        // 自動で送信するかどうか
        $isModeAuto = false;
        $isModeDry = false;
        
        // 結果保存
        $aryResultSend = array();
        $aryResultStop = array();
        $aryResultNotF = array();
        
        
        // 起動オプションから設定を取り出す
        $aryOption = getPhpOption($argv);
        $phpFileName = $aryOption[ARGV_INDEX_PHP_FNAME];
        $confFileName = $aryOption[ARGV_INDEX_CONF_FNAME];
        $smtpServer = $aryOption[ARGV_INDEX_SMTP_SERVER];
        $smtpPortNo = $aryOption[ARGV_INDEX_SMTP_PORT_NO];
        
        // 動作モードの切り替え
        if(mb_strpos($phpFileName, 'auto') !== false) {
            // 自動送信モード
            $isModeAuto = true;
        }
        
        if(mb_strpos($phpFileName, 'dry') !== false
            || $smtpPortNo == 0
        ) {
            // 実際には送信しないモード
            $isModeDry = true;
        }
        
        // ログファイル
        $log = getLog(LOG_LEVEL);
        
        if(empty($confFileName)) {
            // 設定ファイルが不正な場合
            $isViewHelp = true;
        } else {
            
            // 設定ファイルからリストデータを取得してくる
            $confData = readConfigFile($confFileName);
            
            // そのディレクトリが存在するか調べる
            if($confData->isEnabled()) {
                $log->debug(__LINE__.':$confData is');
                
                // メンバーリスト分処理を行う
                foreach($confData->getListMember() as $member) {
                    
                    // リストから該当するディレクトリがあるか調べる
                    if($member->isEnabled()) {
                        $log->debug(__LINE__.':member is '.$member->getName());
                        
                        // 送ってよいか処理の確認
                        // yを待つ
                        if($isModeAuto || confirmMail($member)) {
                            try {
                                // 送信
                                output('メールを送信します');
                                
                                if($isModeDry == false) {
                                    sendMail($member, $smtpServer, $smtpPortNo);
                                }
                                
                                
                                $aryResultSend [] = $member->toStrNameMail();
                                
                            } catch(Exception $e) {
                                output('送信を中止しました。');
                                $aryResultStop [] = $member->toStrNameMail().$e->getMessage();
                            }
                            
                        } else {
                            // 中止
                            output('送信を中止しました。');
                            $aryResultStop [] = $member->toStrNameMail();
                        }
                    } else {
                        // 該当するフォルダがない
                        $aryResultNotF [] = $member->toStrNameMail();
                        
                        output($member->getName().'に該当するフォルダが見つかりませんでした。');
                        $log->debug(__LINE__.':member skip '.$member->getName());
                    }
                }
                
                // 実行結果の出力
                // 送った名前、メルアド、ファイルをログに出す
                dispResult($aryResultSend, $aryResultStop, $aryResultNotF);
                
            } else {
                //
                $isViewHelp = true;
            }
        }
        
        // ヘルプの出力が必要な場合
        if($isViewHelp) {
            dispHelpThis();
        }
        
    } catch(Exception $mainExcep) {
        var_dump($mainExcep);
    }
}


/**
 * Class ConfigData
 */
class ConfigData
{
    private $dirPath;
    private $listMember;
    private $arySkipData;
    
    function __construct()
    {
        $this->dirPath = __DIR__;
        $this->listMember = array();
        $this->arySkipData = array();
    }
    
    /**
     * クラスのプロパティに値が入っているか確認する
     * @author Tomari, ace
     * @return bool 値が入っていればtrue 入ってなければfalse
     */
    public function isEnabled()
    {
        //コンストラクトで入れた値と比較して確認
        if(empty($this->dirPath)
            && empty($this->listMember)
            && empty($this->arySkipData)
        ) {
            return false;
        } else {
            return true;
        }
    }
    
    public function getDirPath()
    {
        return $this->dirPath;
    }
    
    public function getListMember()
    {
        return $this->listMember;
    }
    
    public function getArySkipData()
    {
        return $this->arySkipData;
    }
    
    public function setDirPath($dirPath)
    {
        $this->dirPath = $dirPath;
    }
    
    public function addListMember($member)
    {
        $this->listMember [] = $member;
    }
    
    public function addArySkipData($data)
    {
        $this->arySkipData [] = $data;
    }
    
}

/**
 * Class Member
 */
class Member
{
    
    private $name;
    private $mail;
    private $dirName;
    private $aryFilePath;
    
    function __construct()
    {
        $this->name = '';
        $this->mail = '';
        $this->dirName = '';
        $this->aryFilePath = array();
    }
    
    function toStrNameMail()
    {
        return 'NAME='.$this->name.', MAIL='.$this->mail;
    }
    
    /**
     * クラスのプロパティに値が入っているか確認する
     * @author Tomari, ace
     * @return bool 値が入っていればtrue 入ってなければfalse
     */
    public function isEnabled()
    {
        //コンストラクトで入れた値と比較して確認
        if(empty($this->mail) && empty($this->dirName)) {
            return false;
        } else {
            return true;
        }
    }
    
    static function getPassHeadAry()
    {
        return array("\t", '/', 'x', 'o', 'O');
    }
    
    
    function setName($str)
    {
        $this->name = $str;
    }
    
    function setMail($str)
    {
        $this->mail = $str;
    }
    
    function setDirName($dirName)
    {
        $this->dirName = $dirName;
    }
    
    function getName()
    {
        return $this->name;
    }
    
    function getMail()
    {
        return $this->mail;
    }
    
    function getDirName()
    {
        return $this->dirName;
    }
    
    function getAryFilePath()
    {
        return $this->aryFilePath;
    }
    
    function addFilePath($path)
    {
        $this->aryFilePath [] = $path;
    }
}


/**
 *  PHP起動時のオプションを取得する
 *  起動引数が存在するか、またファイルが存在するかを確認する
 * @author ace
 * @param $argv array 起動時のオプション 第1引数 => ファイルパス(絶対パス もしくは 相対パス)
 * @param $isRealPath bool 戻り値であるファイルパスを絶対パスにするフラグ デフォルトはfalse
 * @return string ファイルパスを返す ファイルがない時は空で返す
 * @throws Exception エラー発生時に呼び出し元の関数に例外を投げる
 */
function getPhpOption($argv, $isRealPath = false)
{
    $result = array();
    
    try {
        if(empty($argv) == false
            && count($argv) == ARGV_INDEX_MAX
        ) {
        
            // PHP実行ファイル
            $result[ARGV_INDEX_PHP_FNAME] = trim(strtolower($argv[ARGV_INDEX_PHP_FNAME]));
        
            // ファイルパス
            $s = $argv[ARGV_INDEX_CONF_FNAME];
            if(file_exists($s)) {
                $result[ARGV_INDEX_CONF_FNAME] = $isRealPath ? realpath($s) : $s;
            } else {
                throw new Exception(__FUNCTION__.'() LINE:'.__LINE__.' argv conf_file is error');
            }
        
            // SMTPサーバのドメイン
            $s = $argv[ARGV_INDEX_SMTP_SERVER];
            if(preg_match('/^[a-zA-Z][a-zA-Z0-9\.\-]+[a-zA-Z]$/', $s)) {
                $result[ARGV_INDEX_SMTP_SERVER] = $s;
            } else {
                throw new Exception(__FUNCTION__.'() LINE:'.__LINE__.' argv smtp_server is error');
            }
        
            // SMTPサーバのポート番号
            $s = $argv[ARGV_INDEX_SMTP_PORT_NO];
            if(preg_match('/[0-9]+/', $s)) {
                $result[ARGV_INDEX_SMTP_PORT_NO] = intval($s);
            } else {
                throw new Exception(__FUNCTION__.'() LINE:'.__LINE__.' argv smtp_port_no is error');
            }
        
        } else {
            throw new Exception(__FUNCTION__.'() LINE:'.__LINE__.' argv is error');
        }
    } catch(Exception $e) {
        throw $e;
    }
    
    return $result;
}


/**
 * 標準出力
 */
function output($str, $encode = OS_ENC)
{
    if(empty($encode)) {
        print $str.PHP_EOL;
        
    } else {
        print mb_convert_encoding($str, $encode, 'UTF-8').PHP_EOL;
    }
}


/**
 * 標準入力を待つ
 * @param num 適当な値
 * @return String エラーが起きた場合は空文字
 */
function input($num = 1024)
{
    $s = fgets(STDIN, $num);
    
    if($s === false) {
        $s = "";
    }
    
    return $s;
}

/**
 * ファイルに出力するログ
 */
function getLog($level = Logger::INFO)
{
    $log = new Logger('LogINF');
    $handler = new StreamHandler('php://stdout', $level);
    $log->pushHandler($handler);
    
    return $log;
}


/**
 * ファイルのパスから中身を読み込み、形式を確認してConfigDataのプロパティに分配する
 * 1行目は個人ファイルが入っているディレクトリへのパスのため分ける
 * 読み込むファイルの存在の有無の確認はここでは行わない
 * @author Tomari
 * @param string $readFilePath 読み込むファイルへのパス
 * @param bool $isAttachHideFile 添付ファイルに隠しファイルを入れるかのフラグ trueなら入れる
 * @return object ConfigDataのインスタンス
 */
function readConfigFile($readFilePath, $isAttachHideFile = false)
{
    //ConfigDataのインスタンスを作成する
    $result = new ConfigData();
    
    try {
        //改行を除いてファイルを読み込む
        $aryFileText = file($readFilePath, FILE_IGNORE_NEW_LINES);
    
        //ファイル内の文章が1行以上存在するか確認
        if(0 < count($aryFileText)) {
            //ファイル1行目にあるディレクトリパスを抜き取る
            $confDirPath = array_shift($aryFileText);
    
            //ファイル1行目にあるパスのディレクトリが存在するか確認
            if(file_exists($confDirPath)) {
                //存在するならパスをプロパティに入れる
                $result->setDirPath($confDirPath);
                //2行目から先のテキストが正しいフォーマットか確認する
                foreach($aryFileText as $sjisText) {
                    $text = mb_convert_encoding($sjisText, 'UTF-8', 'SJIS');
                    
                    $member = new Member();
                    
                    //csv, tsv形式かどうか、行頭にスキップする文字があるか確認
                    if(checkFormatCsvTsv($text)
                        && checkHeadStr($text, getPassHeadAry()) == false
                    ) {
                        
                        //csv, tsv形式で行頭にスキップする文字がないなら文字列を分割する
                        $arySplitText = splitText($text);
                        
                        //文字列の分割が出来ているか確認
                        if(empty($arySplitText) == false) {
                            //出来ているなら名前とメールアドレスに分解する
                            $name = $arySplitText[0];
                            $mail = $arySplitText[1];
                            
                            //名前をプロパティに入れる
                            $member->setName($name);
                            
                            //名前から個人ディレクトリを検索する
                            $dirPath = setEnabledHitDir($confDirPath, mb_convert_encoding($name, 'SJIS', 'UTF-8'));
                            
                            //メールアドレスの形式とディレクトリの存在を確認する
                            if(checkFormatMail($mail)
                                && file_exists($dirPath)
                            ) {
                                //メールアドレスが正しい かつ ディレクトリが存在するなら
                                //メールアドレスと個人ディレクトリへのパスをプロパティに入れる
                                $member->setMail($mail);
                                $member->setDirName($dirPath);
                                //個人ディレクトリ内の一覧を取得し、親ディレクトリ、カレントディレクトリを除く
                                $aryFilePath = scandir($dirPath);
                                $excludeDir = array('.', '..');
                                $aryFilePath = array_diff($aryFilePath, $excludeDir);
                                
                                //添付用ファイルに隠しファイルを入れるか確認する
                                foreach($aryFilePath as $path) {
                                    if($isAttachHideFile == true) {
                                        $member->addFilePath($path);
                                        
                                    } else if(mb_strpos($path, '.') !== 0) {
                                        //隠しファイルを入れないなら、ドットから始まるものを除く
                                        $member->addFilePath($path);
                                    }
                                }
                            }
                        }
                    }
                    
                    //メンバーのインスタンスに値が全て入っているか確認
                    if(($member->isEnabled())) {
                        $result->addListMember($member);
                    } else {
                        $result->addArySkipData($text);
                    }
                }
            }
        }
        
    } catch(Exception $e) {
        throw $e;
    }
    
    return $result;
}

/**
 * 文字列を指定の文字が最初に現れた時点で2分割して配列に入れる
 * @author Tomari
 * @param string $str 分割したい文字列
 * @param string $aryStr 分割部分の文字
 * @return array [0]=>前部分 [1]=>後部分
 */
function splitText($str, $aryStr = array(',', "\t"))
{
    $result = array();
    
    //指定文字が存在するかを調べる
    foreach($aryStr as $cutStr) {
    
        $split = mb_strstr($str, $cutStr, true);
        //変数に文字列が入っているか確認
        if(empty($split) == false) {
            //前部とタブの長さから後部を取得する
            $splitLen = mb_strlen($split.$cutStr);
            
            $result [] = trim($split);
            $result [] = trim(mb_substr($str, $splitLen));
            
            break;
        }
    }
    
    return $result;
}


/**
 *  ファイルから抜き出したテキストが正しい形式か確認する
 * （ 名前,メールアドレス、名前, メールアドレス、名前    メールアドレス ）=> true
 * 全角文字を正規表現に入れると文字コードでヒットしないので外す
 * @author Tomari, ace
 * @param string $text 確認するテキスト
 * @return bool 正しければ true 間違っていれば false
 */
function checkFormatCsvTsv($text)
{
    return (preg_match("<[^ ,]+(,|, |	).+@.+>", $text) == 1) ? true : false;
}


/**
 *  行頭に特定の文字が入っているか確認
 * @author Tomari
 * @param string $text 確認するテキスト
 * @param array $aryCheckWord 確認する文字の配列
 * @return bool あればtrue 無ければfalse
 */
function checkHeadStr($text, $aryCheckWord)
{
    $result = false;
    
    foreach($aryCheckWord as $checkWord) {
        if(mb_strpos($text, $checkWord) === 0) {
            $result = true;
            break;
        }
    }
    
    return $result;
}

/**
 *  行頭に付いていたらスキップする文字の配列を返す関数
 * @author Tomari
 * @return array 文字の配列
 */
function getPassHeadAry()
{
    return array("\t", '/');
}


/**
 * メール送信の確認
 * ユーザに入力を求めてその結果を返す
 */
function confirmMail($member)
{
    $result = false;
    
    output(" ");
    output(" ");
    output(" ");
    output("・名前とメールアドレスを確認してください=================");
    output($member->getName());
    output($member->getMail());
    
    
    output(" > 添付ファイル：".count($member->getAryFilePath()));
    output(" > ".$member->getDirName(), false);
    foreach($member->getAryFilePath() as $path) {
        output(' >> '.$path, ''); // SJISなのでそのまま出力する
    }
    
    output('-----------------------------------------------------');
    output(' yes か no を入力してください');
    
    
    // 入力がいずれかであるならOK
    $str = trim(strtolower(input()));
    if($str == 'yes' || $str == 'ok' || $str == 'yyy') {
        $result = true;
    }
    
    return $result;
}


/**
 * メール送信
 *
 */
function sendMail($member, $server, $port)
{
    
    // SMTPトランスポートを使用
    // SMTPサーバはlocalhost(Poftfix)を使用
    // 他サーバにある場合は、そのホスト名orIPアドレスを指定する
    $transport = \Swift_SmtpTransport::newInstance($server, $port);
    
    // メーラークラスのインスタンスを作成
    $mailer = Swift_Mailer::newInstance($transport);
    
    // メッセージ作成
    $message = Swift_Message::newInstance()
        ->setSubject(getSubject4SendMail($member->getName()))
        ->setTo($member->getMail())
        ->setFrom([MAIL_FROM])
        ->setBody(getBody4SendMail());
    
    // ディレクトリからファイル一覧を取得する
    foreach($member->getAryFilePath() as $fpath) {
        $fullPath = $member->getDirName().DIRECTORY_SEPARATOR.$fpath;
        
        // 添付ファイルが文字化けしないようにエンコードする
        $atch = Swift_Attachment::fromPath($fullPath);
        $atch->setFilename(mb_convert_encoding($atch->getFilename(), 'UTF-8', 'SJIS'));
        
        $message->attach($atch);
    }
    
    
    // メール送信
    return $mailer->send($message);
}

function getSubject4SendMail($name)
{
    return 'タイトルです。'.$name;
}

function getBody4SendMail()
{
    return 'UTF8の本文です。';
}


/**
 * 実行結果の出力
 */
function dispResult($arySend, $aryStop, $aryNotFound)
{
    output("");
    output("「メールを送信した人：".count($arySend)."人」");
    foreach($arySend as $str) {
        output(' > send: '.$str);
    }
    output("-------------------------------------------");
    
    output("");
    output("「メールの送信を中止した人：".count($aryStop)."人」");
    foreach($aryStop as $str) {
        output(' > stop: '.$str);
    }
    output("-------------------------------------------");
    
    output("");
    output("「フォルダが見つからなかった人：".count($aryNotFound)."人」");
    foreach($aryNotFound as $str) {
        output(' > notf: '.$str);
    }
    output("-------------------------------------------");
}

/**
 * このプログラムの使い方を表示する
 */
function dispHelpThis()
{
    /*
    使い方を出力する
     */
    print __FUNCTION__.PHP_EOL;
}


/**
 * 渡されたディレクトリパスの中に指定の文字が含まれるディレクトリがあれば返す
 * @author Tomari
 * @param string $path 対象ディレクトリが入っているディレクトリへのパス
 * @param string $str ディレクトリパスを検索する単語
 * @return string 検索にヒットしたディレクトリのフルパス
 */
function setEnabledHitDir($path, $str)
{
    $result = '';
    $aryDir = scandir($path);
    //ディレクトリの一覧から名前が含まれているものを探す
    foreach($aryDir as $dir) {
        if(mb_strpos($dir, $str) !== false) {
            //存在するならフルパスを渡す
            $result = realpath($path."/".$dir);
            
            break;
        }
    }
    
    return $result;
}


/**
 *  メールアドレスが正しいか判別する関数
 *  第2引数にドメインを入れることで特定のドメインに対応できる
 * @author Tomari
 * @param  string $mail メールアドレス
 * @param  string $domain ドメイン
 * @return bool    $isResult 正しければ true , 間違っていると false
 */
function checkFormatMail($mail, $domain = '')
{
    
    $isResult = false;
    
    $mailMatch = '';
    
    //第2引数の有無で正規表現を切り替える
    if(empty($domain)) {
        //ドメイン指定なし
        $mailMatch = getMatchStrForMail();
    } else {
        
        //ドメイン指定があり
        $accountLen = strlen($mail) - strlen($domain);
        $domainPos = strpos($mail, $domain);
        
        //ドメインが特定の位置から始まっているとき
        if($accountLen == $domainPos) {
            $mailMatch = getMatchStrForMail($domain);
        }
    }
    
    //正規表現と一致するか調べる
    if(empty($mailMatch) == false) {
        $isResult = (preg_match($mailMatch, $mail) == 1) ? true : false;
    }
    
    return $isResult;
}


/**
 *  ドメインの有無によってメールアドレス確認用の正規表現を変更する関数
 * @author Tomari
 * @param  string $domain ドメイン
 * @return string $result  メールアドレス確認用正規表現
 */
function getMatchStrForMail($domain = '')
{
    
    $result = '';
    
    static $BASE = "^[a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|]+([.][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\-]+)*";
    
    if(empty($domain)) {
        //ドメイン指定なし
        $result = $BASE."[@][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\-]+([.][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\- ]+)*$";
    } else {
        //ドメイン指定あり
        $result = $BASE.$domain;
        
    }
    
    return '<'.$result.'>';
}


