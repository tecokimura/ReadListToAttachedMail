<?php
/**
 * Created by PhpStorm.
 * User: ace
 * Date: 2016/09/01
 * Time: 01:24
 * \
 */

define('MAIL_SMTP_SERVER',  '');
define('MAIL_SMTP_PORT_NO', 0);
define('MAIL_FROM',         'abc@abc.jp');


require_once './vendor/autoload.php';
use Monolog\Logger;
use Monolog\Handler\StreamHandler;





// 起動オプション確認、第一引数から設定ファイル名を取得する
// 設定ファイル：一行目：PDFパス。2行目：name,mailaddress
test();
main($argc, $argv);
exit;

/**
 * ライブラリなどのテスト
 */
function test() {

}

/**
 * プログラムのメイン処理
 * @param $argc
 * @param $argv
 */
function main($argc, $argv) {

    $isViewHelp= false;
    $confFileName = getRunOption( $argv );

    $log = getLog();
    $out = getOutput();

    if( empty($confFileName)) {
        // 設定ファイルが不正な場合
        $isViewHelp = true;
    }
    else {

        // 設定ファイルからリストデータを取得してくる
        $confData = readConfigFile($confFileName);

        // そのディレクトリが存在するか調べる
        $memberDirPath = $confData->getDirPath();
        if( isEnabledDir($memberDirPath) ) {

            // メンバーリスト分処理を行う
            foreach($confData->getListMember() as $member) {
                // リストから該当するディレクトリがあるか調べる
                if( $member->setEnabledHitDir($memberDirPath) ) {

                    // ある

                    // 送ってよいか処理の確認
                    // yを待つ
                    if( confirmMail($member) ) {
                        // 送信
                        sendMail($member);
                    } else {
                        // 中止
                    }
                } else {
                    // ない
                    $member->dispNoMember();
                }
            }

            // 実行結果の出力
            // 送った名前、メルアド、ファイルをログに出す
            dispResult();

        } else {
            //
            $isViewHelp = true;
        }
    }


    // ヘルプの出力が必要な場合
    if ($isViewHelp) {
        dispHelpThis();
    }
}


/**
 * Class ConfigData
 */
class ConfigData {
    private $dirPath;
    private $listMember;
    private $arySkipData;

    function __construct() {
        $this->dirPath = __DIR__;
        $this->listMember = array();
        $this->arySkipData = array();
    }

    public function getDirPath() { return $this->dirPath; }
    public function getListMember() { return $this->listMember; }
    public function getArySkipData() { return $this->arySkipData; }
    public function setDirPath($dirPath) { $this->dirPath = $dirPath; }
    public function addListMember($member) { $this->listMember []= $member; }
    public function addArySkipData($data) { $this->arySkipData []= $data; }
    public function addFilePath($path) { $this->aryFilePath []= $path; }

}

/**
 * Class Member
 */
class Member {
    private $name;
    private $mail;
    private $dirName;
    private $aryFilePath;

    function __construct() {
        $this->name = '';
        $this->mail = '';
        $this->dirName = '';
        $this->aryFilePath = array();
    }

    /**
     * 渡されたディレクトリパスの中に自分に当たるディレクトリがあれば設定してtrueを返す
     * @param $path
     * @return bool
     */
    function setEnabledHitDir($path) {
        $result = false;

        print_r($path);

        return $result;
    }

    /**
     * 設定ファイルにはあってもディレクトリがなかった場合の注意文
     */
    static function dispNoMember() {

    }

    static function getPassHeadAry() {
        return array("\t",'/','x','o','O');
    }


    function setName($str) { $this->name = $str; }
    function setMail($str) { $this->mail = $str; }
    function setDirName($dirName) { $this->dirName = $dirName; }
    function getName() { return $this->name; }
    function getMail() { return $this->mail; }
    function getDirName() { return $this->dirName; }

    function addFilePath($path) { $this->aryFilePath []= $path; }
}


/**
 *  PHP起動時のオプションを取得する
 *  起動引数が存在するか、またファイルが存在するかを確認する
 *  @author Tomari
 *  @param $argv array 起動時のオプション 第1引数 => ファイルパス(絶対パス もしくは 相対パス)
 *  @param $isRealPath bool 戻り値であるファイルパスを絶対パスにするフラグ デフォルトはfalse
 *  @return string ファイルパスを返す ファイルがない時は空で返す
 *  @throws Exception エラー発生時に呼び出し元の関数に例外を投げる
 */
function getPhpOption($argv, $isRealPath=false){
    $result = '';
    
    try {
        if( empty( $argv ) == false ){
            //引数があるとき
            array_shift( $argv );
            
            foreach( $argv as $str ){
                if( file_exists( $str ) == true ){
                    //該当するファイルが存在し、$isRealPathがtrueならば絶対パスを渡す
                    $result = ( $isRealPath == true ) ? realpath( $str ) : $str;
                }
                
                break;
            }
        }
        
    }catch ( Exception $e ){
        throw $e;
    }
    
    return $result;
}


/**
 * ファイルに出力するログ
 */
function getLog($level=Logger::DEBUG) {
    $log = new Logger('Log:');
    $handler = new StreamHandler('php://stdout', $level);
    $log->pushHandler($handler);

    return $log;
}


/**
 * 画面出力用のログ
 */
function getOutput($level=Logger::INFO) {
    $log = new Logger('Log:');
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
function readConfigFile($readFilePath, $isAttachHideFile=false) {
    //ConfigDataのインスタンスを作成する
    $result = new ConfigData();
    
    try{
        //改行を除いてファイルを読み込む
        $aryFileText = file( $readFilePath, FILE_IGNORE_NEW_LINES );
        
        //ファイル内の文章が1行以上存在するか確認
        if( 0 < count($aryFileText) ) {
            //ファイル1行目にあるディレクトリパスを抜き取る
            $confDirPath = array_shift( $aryFileText );
            
            //ファイル1行目にあるパスのディレクトリが存在するか確認
            if( file_exists($confDirPath) ) {
                //存在するならパスをプロパティに入れる
                $result->setDirPath( $confDirPath );

                //2行目から先のテキストが正しいフォーマットか確認する
                foreach( $aryFileText as $text ) {
                    $member = new Member();
                    
                    //csv, tsv形式かどうか、行頭にスキップする文字があるか確認
                    if( checkFormatCsvTsv($text)
                     && checkHeadStr($text, getPassHeadAry()) == false ) {
                        
                        //csv, tsv形式で行頭にスキップする文字がないなら文字列を分割する
                        $arySplitText = splitText($text);
                        
                        //文字列の分割が出来ているか確認
                        if( empty($arySplitText) == false ){
                            //出来ているなら名前とメールアドレスに分解する
                            $name = $arySplitText[0];
                            $mail = $arySplitText[1];
                            
                            //名前をプロパティに入れる
                            $member->setName( $name );

                            //名前から個人ディレクトリを検索する
                            $dirPath = setEnabledHitDir($confDirPath, $name);

                            //メールアドレスの形式とディレクトリの存在を確認する
                            if( checkFormatMail($mail)
                             && file_exists($dirPath) ) {
                                //メールアドレスが正しい かつ ディレクトリが存在するなら
                                //メールアドレスと個人ディレクトリへのパスをプロパティに入れる
                                $member->setMail($mail);
                                $member->setDirName( $dirPath );
                                
                                //個人ディレクトリ内の一覧を取得し、親ディレクトリ、カレントディレクトリを除く
                                $aryFilePath = scandir($dirPath);
                                $excludeDir = array( '.', '..' );
                                $aryFilePath = array_diff( $aryFilePath, $excludeDir );

                                //添付用ファイルに隠しファイルを入れるか確認する
                                foreach( $aryFilePath as $path ) {
                                    if( $isAttachHideFile == true ) {
                                        $member->addFilePath($path);

                                    } else if( mb_strpos($path, '.') !== 0 ) {
                                        //隠しファイルを入れないなら、ドットから始まるものを除く
                                        $member->addFilePath( $path );
                                    }
                                }
                            }   
                        }
                    }
                    
                    //メンバーのインスタンスに値が全て入っているか確認
                    if( ($member->isEnable()) ) {
                        $result->addListMember( $member );

                    } else {
                        $result->addArySkipData( $text );
                    }
                }
            }
        }
        
    } catch( Exception $e ) {
        throw $e;
    }
    
    return $result;
}

/**
 * 名前リストからひとつづつ設定して返す
 * @author ace
 * @param $aryDataStr
 * @return array
 */
function setMemberList($aryDataStr) {
    $result = array();
    foreach($aryDataStr as $dataStr) {
        $member = new Member();
        if( checkFormatCsvTsv($dataStr, $member) ) {
            $result []= $member;
        }
    }

    return $result;
}


/**
 * CSV,TSVで名前とメルアドがつながってるか調べる
 * @author ace, tomari
 * @param String $line 設定ファイルの一行
 * @param Member $member nullじゃなければ設定する
 * @return bool 読み込めたかどうか
 */
function checkFormatCsvTsv($line, $member=null) {
    $result = false;

    // csv,tsvになっている
    // メルアドが正しい
    // パスする文字列が入っていない()
    //  ならばset

    return $result;
}


/**
 *  行頭に特定の文字が入っているか確認
 *  @author Tomari
 *  @param string $text 確認するテキスト
 *  @param array $aryCheckWord 確認する文字の配列
 *  @return bool あればtrue 無ければfalse
 */
function checkHeadStr( $text, $aryCheckWord ) {   
    $result = false;
    
    foreach ( $aryCheckWord as $checkWord ) {
        if( mb_strpos( $text, $checkWord ) === 0 ) {
            $result = true;
            break;
        }
    }
    
    return $result; 
}


/**
 * 渡されたパスが有効なディレクトリかどうか
 * @author ace, tomari
 */
function isEnabledDir($path) {
    return false;
}

/**
 * メール送信の確認
 *
 */
function confirmMail($member) {

/*    名前、メルアド添付ファイル名をだして本当に送っていいか確認する
    userの入力をまってyの場合はおくる*/

    return false;
}


/**
 * メール送信
 *
 */
function sendMail($member, $server=SMTP_SERVER, $port=SMTP_PORTNO) {

    // SMTPトランスポートを使用
    // SMTPサーバはlocalhost(Poftfix)を使用
    // 他サーバにある場合は、そのホスト名orIPアドレスを指定する
    $transport = \Swift_SmtpTransport::newInstance($server, $port);

    // メーラークラスのインスタンスを作成
    $mailer = Swift_Mailer::newInstance($transport);

    // メッセージ作成
    $message = Swift_Message::newInstance()
        ->setSubject(getSubject4SendMail())
        ->setTo($member->getMail())
        ->setFrom([MAIL_FROM])
        ->setBody(getBody4SendMail());

    // ディレクトリからファイル一覧を取得する
    foreach($member->aryFilePath as $fpath) {
        $message->attach(Swift_Attachment::fromPath($fpath));
    }


    // メール送信
    return $mailer->send($message);;
}

function getSubject4SendMail($name) {
    return 'Hello '.$name;
}

function getBody4SendMail() {
    return 'こんにちは';
}


/**
 * 実行結果の出力
 */
function dispResult() {
    // 誰にどのファイルを送ったか
}

/**
 * このプログラムの使い方を表示する
 */
function dispHelpThis() {
    /*
    使い方を出力する
     */
    print __FUNCTION__.PHP_EOL;
}


/**
 * 名前にあたるものが指定されたディレクトリにあるかどうか
 * @author ace, tomari
 * @param $dirPath 調べるディレクトリ
 * @param $name 調べる名前
 * @return string ヒットしたディレクトリ名
 */
function isEnabledHitDir($dirPath, $name) {
    $result = NULL;

    return $result;
}


/**
 *  メールアドレスが正しいか判別する関数
 *  第2引数にドメインを入れることで特定のドメインに対応できる
 *  @author Tomari
 *  @param  string  $mail  メールアドレス
 *  @param  string  $domain  ドメイン
 *  @return bool    $isResult 正しければ true , 間違っていると false
 */
function checkFormatMail( $mail, $domain = '' ){

    $isResult = false ;

    $mailMatch = '';

    //第2引数の有無で正規表現を切り替える
    if( empty( $domain ) ){

        //ドメイン指定なし
        $mailMatch = getMatchStrForMail();

    }else{

        //ドメイン指定があり
        $accountLen = strlen( $mail ) - strlen( $domain );

        $domainPos = strpos( $mail, $domain );

        //ドメインが特定の位置から始まっているとき
        if( $accountLen == $domainPos ){

            $mailMatch = getMatchStrForMail( $domain );

        }
    }

    //正規表現と一致するか調べる
    if( empty( $mailMatch ) == false ){

        $isResult = ( preg_match( $mailMatch, $mail ) == 1 ) ? true : false;
    }

    return $isResult;
}



/**
 *  ドメインの有無によってメールアドレス確認用の正規表現を変更する関数
 *  @author Tomari
 *  @param  string $domain  ドメイン
 *  @return string $result  メールアドレス確認用正規表現
 */
function getMatchStrForMail( $domain ='' ){

    $result = '';

    static $BASE = "^[a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|]+([.][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\-]+)*";

    if( empty( $domain ) ){

        //ドメイン指定なし
        $result = $BASE . "[@][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\-]+([.][a-zA-Z0-9_!#\$\%&'*+/=?\^`{}~|\- ]+)*$";

    }else{

        //ドメイン指定あり
        $result = $BASE . $domain;

    }

    return '<' . $result . '>';
}


