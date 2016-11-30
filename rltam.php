<?php
/**
 * リストから添付ファイルを探してメールで送信するプログラム
 * php rltam.php ${CONF_FNAME} ${SMTP_SERVER} ${SMTP_PORTNO}
 * php rltam-auto.php ${CONF_FNAME} ${SMTP_SERVER} ${SMTP_PORTNO}
 * php rltam-dry.php ${CONF_FNAME} ${SMTP_SERVER} ${SMTP_PORTNO}
 * php rltam-debug.php ${CONF_FNAME} ${SMTP_SERVER} ${SMTP_PORTNO}
 *  とファイル名を変えることで動作モードを変更できる（複合も可能です）
 * UTF8のPHPがSJISのファイルを読む
 * 1行目はWindowsファイルパスなのでSJISのまま使う
 * 2行目以降は設定値なのでUTF-8にして使用する
 *
 * PHP Version 7
 *
 * @category Class
 * @package  None
 * @author   tecokimura <tecokimura@gmail.com>
 * @license  MIT License
 * @link     https://github.com/tecokimura/ReadListToAttachedMail
 *
 * Created by PhpStorm.
 * User: ace
 * Date: 2016/09/01
 * Time: 01:24
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


// 起動オプション確認、第一引数から設定ファイル名を取得する
// 設定ファイル：一行目：PDFパス。2行目：name,mailaddress
if (isset($argc) && isset($argv)) {
    main($argc, $argv);
    exit;
}


/**
 * プログラムのメイン処理
 * 設定ファイルから必要なファイルを添付してメールを送信する
 *
 * @param int      $argc 起動引数がいくつあるか
 * @param String[] $argv 起動引数の文字列
 *
 * @return void 戻り値なし
 */
function main($argc, $argv)
{
    try {
        // 汎用変数
        $str = "";

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

        // 自動送信モードの切り替え
        if (mb_strpos($phpFileName, 'auto') !== false) {
            $isModeAuto = true;
        }

        // 送信しないモード
        if (mb_strpos($phpFileName, 'dry') !== false || $smtpPortNo == 0) {
            $isModeDry = true;
        }

        // ログ出力モード
        $logLevel = Logger::NOTICE;
        if (mb_strpos($phpFileName, 'debug') !== false
            || mb_strpos($phpFileName, 'log') !== false
        ) {
            $logLevel = Logger::DEBUG;
        }
        $logLevel = Logger::DEBUG;
    
        $log = getLog($logLevel);


        // 状況出力
        if ($isModeAuto) {
            $log->debug(__LINE__ . ': set Auto mode');
        }

        if ($isModeDry) {
            $log->debug(__LINE__ . ': set Dry mode');
        }


        if (empty($confFileName)) {
            // 設定ファイルが不正な場合
            $isViewHelp = true;
            $log->debug(__LINE__ . ': arg confFileName is empty : ' . $confFileName);
            return;
        }


        // 設定ファイルからリストデータを取得してくる
        $confData = readConfigFile($confFileName, $log);

        // 対象となるデータが入っているかどうか
        if($confData->isEnabled()) {

            $log->debug(__LINE__ . ': $confData->isEnabled() is true');
            $log->debug(__LINE__ . ': $confData->getListMember() count is true');

            // メンバーリスト分処理を行う
            foreach ($confData->getListMember() as $member) {

                // リストから該当するディレクトリがあるか調べる
                if ($member->isDirName()) {
                    $str = $member->getName() . ' is Enabled() true';
                    $log->debug(__LINE__.': ' . $str);

                    // 送ってよいか処理の確認
                    // yを待つ
                    if ($isModeAuto || confirmMail($member)) {
                        try {
                            // 送信
                            output('メールを送信します');

                            if ($isModeDry == false) {
                                $str = $member->getName() . ' is Enabled() true';
                                $log->debug(__LINE__ . ': ' . $str);
                                sendMail($member, $smtpServer, $smtpPortNo);
                            }


                            $aryResultSend []= $member->toStrNameMail();

                        } catch (Exception $e) {
                            output('送信を中止しました。');
                            $str = $member->toStrNameMail() . $e->getMessage();
                            $aryResultStop []= $str;
                            $log->debug(__LINE__.': sendMail is Exception '.$e);
                        }

                    } else {
                        // 中止
                        output('送信を中止しました。');
                        $aryResultStop [] = $member->toStrNameMail();
                        $str = 'sendMail is stop :' . $member->toStrNameMail();
                        $log->debug(__LINE__.': '.$str);
                    }
                } else {
                    $str = $member->getName() . ' is Enabled() false';
                    $log->debug(__LINE__.': '.$str);
                    // 該当するフォルダがない
                    $aryResultNotF [] = $member->toStrNameMail();

                    output($member->getName() . 'に該当するフォルダが見つかりませんでした。');

                    $str = 'skip '.$member->getName().' Folder is Not Found';
                    $log->debug(__LINE__.':'.$str);
                }
            }

            // 実行結果の出力
            // 送った名前、メルアド、ファイルをログに出す
            displayResult("スキップしたデータ",      ' > skip: ', $aryResultSend);
            displayResult("メールを送信した人",      ' > send: ', $aryResultStop);
            displayResult("メールの送信を中止した人", ' > stop: ', $aryResultNotF);
            displayResult("フォルダが見つからない人", ' > notf: ', $confData->getArySkipData());

        } else {
            //
            $isViewHelp = true;
        }

    } catch (Exception $mainExcep) {
        $isViewHelp = true;
        var_dump($mainExcep);
    }


    // ヘルプの出力が必要な場合
    if ($isViewHelp) {
        dispHelpThis();
    }

}


/**
 * 設定ファイルを管理するクラス
 *
 * @category Class
 * @package  None
 * @author   tecokimura <tecokimura@gmail.com>
 * @license  MIT License
 * @link     https://github.com/tecokimura/ReadListToAttachedMail
 */
class ConfigData
{
    private $_dirPath;
    private $_listMember;
    private $_arySkipData;

    /**
     * ConfigData constructor.
     */
    function __construct()
    {
        $this->_dirPath = __DIR__;
        $this->_listMember = array();
        $this->_arySkipData = array();
    }

    /**
     * クラスのプロパティに値が入っているか確認する
     *
     * @author Tomari, ace
     *
     * @return bool 値が入っていればtrue 入ってなければfalse
     */
    public function isEnabled()
    {
        //コンストラクトで入れた値と比較して確認
        if (empty($this->_dirPath)
            && empty($this->_listMember)
            && empty($this->_arySkipData)
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Getter for _dirPath
     *
     * @return string
     */
    public function getDirPath()
    {
        return $this->_dirPath;
    }

    /**
     * Getter for _listMember
     *
     * @return array
     */
    public function getListMember()
    {
        return $this->_listMember;
    }

    /**
     * Getter for _arySkipData
     *
     * @return array
     */
    public function getArySkipData()
    {
        return $this->_arySkipData;
    }

    /**
     * Getter for _dirPath
     *
     * @param string $_dirPath 設定するディレクトリのパス
     *
     * @return void 戻り値なし
     */
    public function setDirPath($_dirPath)
    {
        $this->_dirPath = $_dirPath;
    }

    /**
     * Add object at array for _listMember
     *
     * @param object $member 追加するMember class のデータ
     *
     * @return void 戻り値なし
     */
    public function addListMember($member)
    {
        $this->_listMember [] = $member;
    }

    /**
     * Add object for _arySkipData
     *
     * @param string $data 追加するデータ
     *
     * @return void 戻り値なし
     */
    public function addArySkipData($data)
    {
        $this->_arySkipData [] = $data;
    }

}

/**
 * 名前やメルアドなどを管理するクラス
 *
 * @category Class
 * @package  None
 * @author   tecokimura <tecokimura@gmail.com>
 * @license  MIT License
 * @link     https://github.com/tecokimura/ReadListToAttachedMail
 */
class Member
{

    private $_name;
    private $_mail;
    private $_dirName;
    private $_aryFilePath;

    /**
     * Member constructor.
     */
    function __construct()
    {
        $this->_name = '';
        $this->_mail = '';
        $this->_dirName = '';
        $this->_aryFilePath = array();
    }

    /**
     * オブジェクトを文字列表現に変える
     *
     * @return string 文字列表現にしたもの
     */
    function toStrNameMail()
    {
        return 'NAME=' . $this->_name . ', MAIL=' . $this->_mail;
    }

    /**
     * クラスのプロパティに値が入っているか確認する
     *
     * @author Tomari, ace
     *
     * @return bool 値が入っていればtrue 入ってなければfalse
     */
    public function isEnabled()
    {
        //コンストラクトで入れた値と比較して確認
        if (empty($this->_name) && empty($this->_mail)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * クラスのdirNameに値が入っているか確認する
     *
     * @author ace
     *
     * @return bool 値が入っていればtrue 入ってなければfalse
     */
    public function isDirName()
    {
        return !empty($this->_dirName);
    }

    /**
     * 行頭のスキップ判定文字
     *
     * @return array
     */
    static function getPassHeadAry()
    {
        return array("\t", '/', 'x', 'o', 'O');
    }


    /**
     * Setter for _name
     *
     * @param string $str 設定する名前
     *
     * @return void 戻り値なし
     */
    function setName($str)
    {
        $this->_name = $str;
    }

    /**
     * Setter for _mail
     *
     * @param string $str 設定するメールアドレス
     *
     * @return void 戻り値なし
     */
    function setMail($str)
    {
        $this->_mail = $str;
    }

    /**
     * Setter for _dirName
     *
     * @param string $_dirName 設定するディレクトリ名
     *
     * @return void 戻り値なし
     */
    function setDirName($_dirName)
    {
        $this->_dirName = $_dirName;
    }

    /**
     * Getter for _name
     *
     * @return string 設定されている名前
     */
    function getName()
    {
        return $this->_name;
    }

    /**
     * Gtter for _mail
     *
     * @return string 設定されているメールアドレス
     */
    function getMail()
    {
        return $this->_mail;
    }

    /**
     * Getter for _name
     *
     * @return string 設定されているディレクトリ名
     */
    function getDirName()
    {
        return $this->_dirName;
    }

    /**
     * Getter for _aryFilePath
     *
     * @return array 設定されているファイルパスの配列
     */
    function getAryFilePath()
    {
        return $this->_aryFilePath;
    }

    /**
     * Add string at _aryFilePath
     *
     * @param string $path 追加したいパス文字列
     *
     * @return void 戻り値なし
     */
    function addFilePath($path)
    {
        $this->_aryFilePath [] = $path;
    }
}


/**
 * PHP起動時のオプションを取得する
 * 起動引数が存在するか、またファイルが存在するかを確認する
 *
 * @param string[] $argv       起動時のオプション 第1引数 => ファイルパス(絶対パス もしくは 相対パス)
 * @param bool     $isRealPath 戻り値であるファイルパスを絶対パスにするフラグ デフォルトはfalse
 *
 * @return string    ファイルパスを返す ファイルがない時は空で返す
 * @throws Exception エラー発生時に呼び出し元の関数に例外を投げる
 */
function getPhpOption($argv, $isRealPath = false)
{
    $result = array();

    try {
        if (empty($argv) == false
            && count($argv) == ARGV_INDEX_MAX
        ) {

            // PHP実行ファイル
            $str = trim(strtolower($argv[ARGV_INDEX_PHP_FNAME]));
            $result[ARGV_INDEX_PHP_FNAME] = $str;

            // ファイルパス
            $s = $argv[ARGV_INDEX_CONF_FNAME];
            if (file_exists($s)) {
                $result[ARGV_INDEX_CONF_FNAME] = $isRealPath ? realpath($s) : $s;
            } else {
                throw new ArgvConfFileException();
            }

            // SMTPサーバのドメイン
            $s = $argv[ARGV_INDEX_SMTP_SERVER];
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9\.\-]+[a-zA-Z]$/', $s)) {
                $result[ARGV_INDEX_SMTP_SERVER] = $s;
            } else {
                throw new ArgvSmtpServerException();
            }

            // SMTPサーバのポート番号
            $s = $argv[ARGV_INDEX_SMTP_PORT_NO];
            if (preg_match('/[0-9]+/', $s)) {
                $result[ARGV_INDEX_SMTP_PORT_NO] = intval($s);
            } else {
                throw new ArgvSmtpPortException();
            }

        } else {
            throw new ArgvException();
        }
    } catch (Exception $e) {
        throw $e;
    }

    return $result;
}


/**
 * オリジナル標準出力
 *
 * @param string $str    出力する文字列
 * @param string $encode エンコード方法
 *
 * @return void 戻り値なし
 */
function output($str, $encode = OS_ENC)
{
    if (empty($encode)) {
        print $str . PHP_EOL;

    } else {
        print mb_convert_encoding($str, $encode, 'UTF-8') . PHP_EOL;
    }
}


/**
 * 標準入力で入力された文字列を返す
 *
 * @param int $num 入力してもらうバッファサイズ
 *
 * @return String エラーが起きた場合は空文字
 */
function input($num = 1024)
{
    $s = fgets(STDIN, $num);

    if ($s === false) {
        $s = "";
    }

    return $s;
}

/**
 * ファイルに出力するログ
 *
 * @param int $level ログの出力レベル
 *
 * @return Logger
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
 *
 * @param string $readFilePath     読み込むファイルへのパス
 * @param Logger $log              ログ出力するためのオブジェクト
 * @param bool   $isAttachHideFile 添付ファイルに隠しファイルを入れるかのフラグ trueなら入れる
 *
 * @return object ConfigDataのインスタンス
 */
function readConfigFile($readFilePath, Logger $log, $isAttachHideFile = false)
{
    $log->debug(__FUNCTION__ . '(' . __LINE__ . '): START ======');

    //ConfigDataのインスタンスを作成する
    $result = new ConfigData();

    try {
        //改行を除いてファイルを読み込む
        $aryFileText = file($readFilePath, FILE_IGNORE_NEW_LINES);
        $log->debug(__FUNCTION__ . '(' . __LINE__ . '): ' . $readFilePath);

        //ファイル内の文章が1行以上ない場合は終了
        if (count($aryFileText) <= 0) {
            return $result;
        }

        //ファイル1行目にあるディレクトリパスを抜き取る
        $confDirPath = array_shift($aryFileText);
        $log->debug(__FUNCTION__ . '(' . __LINE__ . '): ' . $confDirPath);

        //ファイル1行目にあるパスのディレクトリが存在するか確認
        if (file_exists($confDirPath) == false) {
            return $result;
        }

        //存在するならパスをプロパティに入れる
        $result->setDirPath($confDirPath);
        //2行目から先のテキストが正しいフォーマットか確認する
        foreach ($aryFileText as $sjisText) {
            $text = encShiftJISToUtf8($sjisText);
            $log->debug(__FUNCTION__ . '(' . __LINE__ . '): ' . $text);

            $member = new Member();

            //csv, tsv形式かどうか、行頭にスキップする文字があるか確認
            if (checkFormatCsvTsv($text)
                && checkHeadStr($text, getPassHeadAry()) == false
            ) {

                //csv, tsv形式で行頭にスキップする文字がないなら文字列を分割する
                $arySplitText = splitText($text);

                //文字列の分割が出来ているか確認
                if (empty($arySplitText) == false) {
                    //出来ているなら名前とメールアドレスに分解する
                    $name = $arySplitText[0];
                    $mail = $arySplitText[1];

                    $str = 'name=' . $name . ', mail=' . $mail;
                    $log->debug(__FUNCTION__ . '(' . __LINE__ . '): '.$str);

                    //名前をプロパティに入れる
                    $member->setName($name);

                    //名前から個人ディレクトリを検索する
                    $str = encUtf8ToShiftJIS($name);
                    $dirPath = setEnabledHitDir($confDirPath, $str);
    
                    $str = $dirPath.' mail='.$mail;
                    $log->debug(__FUNCTION__.'('.__LINE__.'): '.$str);

                    //メールアドレスの形式とディレクトリの存在を確認する
                    if (checkFormatMail($mail)) {
                        $member->setMail($mail);

                        if (file_exists($dirPath)) {
                            //メールアドレスが正しい かつ ディレクトリが存在するなら
                            //メールアドレスと個人ディレクトリへのパスをプロパティに入れる
                            $member->setDirName($dirPath);
                            //個人ディレクトリ内の一覧を取得し、親ディレクトリ、カレントディレクトリを除く
                            $aryFilePath = scandir($dirPath);
                            $excludeDir  = array('.', '..');
                            $aryFilePath = array_diff($aryFilePath, $excludeDir);

                            // 添付用ファイルに隠しファイルを入れるか確認する
                            foreach ($aryFilePath as $path) {
                                if ($isAttachHideFile == true) {
                                    $member->addFilePath($path);

                                } else if (mb_strpos($path, '.') !== 0) {
                                    //隠しファイルを入れないなら、ドットから始まるものを除く
                                    $member->addFilePath($path);
                                }
                            }
                        }
                    }
                }
            }

            //メンバーのインスタンスに値が全て入っているか確認
            if (($member->isEnabled())) {
                $result->addListMember($member);

                $str = '$result->addListMember()';
                $log->debug(__FUNCTION__.'('.__LINE__.'): '.$str);
            } else {
                $result->addArySkipData($text);

                $str = '$result->addArySkipData()';
                $log->debug(__FUNCTION__.'('.__LINE__.'): '.$str);
            }
        }

    } catch (Exception $e) {
        throw $e;
    }

    $log->debug(__FUNCTION__ . '(' . __LINE__ . '): END =========');

    return $result;
}

/**
 * 文字列を指定の文字が最初に現れた時点で2分割して配列に入れる
 *
 * @param string $str    分割したい文字列
 * @param string $aryStr 分割部分の文字
 *
 * @return array [0]=>前部分 [1]=>後部分
 */
function splitText($str, $aryStr = array(',', "\t"))
{
    $result = array();

    //指定文字が存在するかを調べる
    foreach ($aryStr as $cutStr) {

        $split = mb_strstr($str, $cutStr, true);
        //変数に文字列が入っているか確認
        if (empty($split) == false) {
            //前部とタブの長さから後部を取得する
            $splitLen = mb_strlen($split . $cutStr);

            $result [] = trim($split);
            $result [] = trim(mb_substr($str, $splitLen));

            break;
        }
    }

    return $result;
}


/**
 * ファイルから抜き出したテキストが正しい形式か確認する
 * （ 名前,メールアドレス、名前, メールアドレス、名前    メールアドレス ）=> true
 * 全角文字を正規表現に入れると文字コードでヒットしないので外す
 *
 * @param string $text 確認するテキスト
 *
 * @return bool 正しければtrue間違っていればfalse
 */
function checkFormatCsvTsv($text)
{
    return (preg_match("<[^ ,]+(,|, |	).+@.+>", $text) == 1) ? true : false;
}


/**
 * 行頭に特定の文字が入っているか確認
 *
 * @param string   $text         確認するテキスト
 * @param string[] $aryCheckWord 確認する文字の配列
 *
 * @return bool あればtrue無ければfalse
 */
function checkHeadStr($text, $aryCheckWord)
{
    $result = false;

    foreach ($aryCheckWord as $checkWord) {
        if (mb_strpos($text, $checkWord) === 0) {
            $result = true;
            break;
        }
    }

    return $result;
}

/**
 * 行頭に付いていたらスキップする文字の配列を返す関数
 *
 * @return array 文字の配列
 */
function getPassHeadAry()
{
    return array("\t", '/');
}


/**
 * メール送信の確認
 * ユーザに入力を求めてその結果を返す
 *
 * @param Member $member メール送信の確認処理
 *
 * @return bool 送る場合はtrue,送らない場合はfalse
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


    output(" > 添付ファイル：" . count($member->getAryFilePath()));
    output(" > " . $member->getDirName(), false);
    foreach ($member->getAryFilePath() as $path) {
        output(' >> ' . $path, ''); // SJISなのでそのまま出力する
    }

    output('-----------------------------------------------------');
    output(' yes か no を入力してください');


    // 入力がいずれかであるならOK
    $str = trim(strtolower(input()));
    if ($str == 'yes' || $str == 'ok' || $str == 'yyy') {
        $result = true;
    }

    return $result;
}


/**
 * メール送信処理
 *
 * @param Member $member メールを送るデータ
 * @param string $server smtpサーバのドメイン名
 * @param int    $port   smtpサーバのポート番号
 *
 * @return int メールを送信した件数
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
        ->setBody(getBody4SendMail($member->getName()));

    // ディレクトリからファイル一覧を取得する
    foreach ($member->getAryFilePath() as $fpath) {
        $fullPath = $member->getDirName() . DIRECTORY_SEPARATOR . $fpath;

        // 添付ファイルが文字化けしないようにエンコードする
        $atch = Swift_Attachment::fromPath($fullPath);
        $atch->setFilename(encShiftJISToUtf8($atch->getFilename()));

        $message->attach($atch);
    }


    // メール送信
    return $mailer->send($message);
}


/**
 * メールを送信する際のメールタイトル
 *
 * @param string $name 送る相手の名前
 *
 * @return string メールタイトル文
 */
function getSubject4SendMail($name)
{
    return 'タイトル';
}


/**
 * メールを送信する際のメール本文
 *
 * @param string $name 送る相手の名前
 *
 * @return string メール本文
 */
function getBody4SendMail($name)
{

    $msg = <<<EOM
本文
EOM;

    return $msg;
}


/**
 * メール結果出力
 *
 * @param string   $title   タイトル文字列
 * @param string   $headStr 文字出力の先頭文字列
 * @param string[] $aryStr  出力する文字列配列
 *
 * @return void 戻り値なし
 */
function displayResult($title, $headStr, $aryStr)
{
    output('');
    output('「'.$title.'：count=' . count($aryStr) . '」');
    foreach ($aryStr as $str) {
        output($headStr . $str);
    }
    output("-------------------------------------------");
}


/**
 * このプログラムの使い方を表示する
 *
 * @param string $dispEnc 出力文字コード
 *
 * @return void 戻り値なし
 */
function dispHelpThis($dispEnc = "SJIS")
{
    $msg = <<<EOM
*****************************

How to use?
rltam.php - ReadListToAttachedMail -

argv[n]
[0] = rltam.php
[1] = config file name
[2] = SMTP Server Name
[3] = SMTP Port No

*****************************
EOM;


    // エンコードの指定がある場合
    // 一応日本語の説明文が入ってもいいように
    if (empty($dispEnc) == false) {
        $msg = mb_convert_encoding($msg, $dispEnc);
    }

    print $msg . PHP_EOL;

}


/**
 * 渡されたディレクトリパスの中に指定の文字が含まれるディレクトリがあれば返す
 *
 * @param string $path 対象ディレクトリが入っているディレクトリへのパス
 * @param string $str  ディレクトリパスを検索する単語
 *
 * @return string 検索にヒットしたディレクトリのフルパス
 */
function setEnabledHitDir($path, $str)
{
    $result = '';
    $aryDir = scandir($path);
    //ディレクトリの一覧から名前が含まれているものを探す
    foreach ($aryDir as $dir) {
        if (mb_strpos($dir, $str) !== false) {
            //存在するならフルパスを渡す
            $result = realpath($path . "/" . $dir);

            break;
        }
    }

    return $result;
}


/**
 * メールアドレスが正しいか判別する関数
 * 第2引数にドメインを入れることで特定のドメインに対応できる
 *
 * @param string $mail   メールアドレス
 * @param string $domain ドメイン
 *
 * @return bool $isResult 正しければtrue, 間違っているとfalse
 */
function checkFormatMail($mail, $domain = '')
{

    $isResult = false;

    $mailMatch = '';

    //第2引数の有無で正規表現を切り替える
    if (empty($domain)) {
        //ドメイン指定なし
        $mailMatch = getMatchStrForMail();
    } else {

        //ドメイン指定があり
        $accountLen = strlen($mail) - strlen($domain);
        $domainPos = strpos($mail, $domain);

        //ドメインが特定の位置から始まっているとき
        if ($accountLen == $domainPos) {
            $mailMatch = getMatchStrForMail($domain);
        }
    }

    //正規表現と一致するか調べる
    if (empty($mailMatch) == false) {
        $isResult = (preg_match($mailMatch, $mail) == 1) ? true : false;
    }

    return $isResult;
}


/**
 * ドメインの有無によってメールアドレス確認用の正規表現を変更する関数
 *
 * @param string $domain ドメイン
 *
 * @return string $result メールアドレス確認用正規表現
 */
function getMatchStrForMail($domain = '')
{

    $result = '';
    
    static $BASE = "^[a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|]+([.][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\-]+)*";

    if (empty($domain)) {
        //ドメイン指定なし
        $result = $BASE."[@][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\-]+([.][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\- ]+)*$";
    } else {
        //ドメイン指定あり
        $result = $BASE . $domain;

    }

    return '<' . $result . '>';
}


/**
 * SJISの文字をUTF-8にして返す
 *
 * @param string $str 変換する文字列
 *
 * @return string 変換した文字列
 */
function encShiftJISToUtf8($str)
{
    return mb_convert_encoding($str, 'UTF-8', 'sjis');
}

/**
 * UTF-8の文字をSJISにして返す
 *
 * @param string $str 変換する文字列
 *
 * @return string 変換した文字列
 */
function encUtf8ToShiftJIS($str)
{
    return mb_convert_encoding($str, 'sjis', 'UTF-8');
}


/**
 * Class ArgvException
 *
 * @category Exception_Class
 * @package  None
 * @author   tecokimura <tecokimura@gmail.com>
 * @license  MIT License
 * @link     https://github.com/tecokimura/ReadListToAttachedMail
 */
class ArgvException extends Exception
{
}


/**
 * Class ArgvConfFileException
 *
 * @category Exception_Class
 * @package  None
 * @author   tecokimura <tecokimura@gmail.com>
 * @license  MIT License
 * @link     https://github.com/tecokimura/ReadListToAttachedMail
 */
class ArgvConfFileException extends Exception
{
}


/**
 * Class ArgvSmtpServerException
 *
 * @category Exception_Class
 * @package  None
 * @author   tecokimura <tecokimura@gmail.com>
 * @license  MIT License
 * @link     https://github.com/tecokimura/ReadListToAttachedMail
 */
class ArgvSmtpServerException extends Exception
{
}


/**
 * Class ArgvSmtpPortException
 *
 * @category Exception_Class
 * @package  None
 * @author   tecokimura <tecokimura@gmail.com>
 * @license  MIT License
 * @link     https://github.com/tecokimura/ReadListToAttachedMail
 */
class ArgvSmtpPortException extends Exception
{
}