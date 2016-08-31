<?php
/**
 * Created by PhpStorm.
 * User: ace
 * Date: 2016/09/01
 * Time: 01:24
 * \
 */

require_once './vendor/autoload.php';
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


// SwiftMailerの確認
$mail = Swift_SmtpTransport::newInstance('TEST', 25);

$log = new Logger('Log:');
$handler = new StreamHandler('php://stdout', Logger::DEBUG);
$log->pushHandler($handler);


$log->debug("abcdefg");



// 起動オプション確認、第一引数から設定ファイル名を取得する
// 設定ファイル：一行目：PDFパス。2行目：name,mailaddress

main($argc, $argv);
exit;

/**
 * プログラムのメイン処理
 * @param $argc
 * @param $argv
 */
function main($argc, $argv) {

    $isViewHelp= false;
    $confFileName = getRunOption($argc, $argv);

    if( empty($confFileName)) {
        // 設定ファイルが不正な場合
        $isViewHelp = true;
    }
    else {
        // 設定ファイルから最初の一行を取得してくる（データのパス）
        $pathDataDir = readConfig($confFileName, 1, 1);

        // そのディレクトリが存在するか調べる
        if( isEnabledDir($pathDataDir) ) {
            // 設定ファイルの2行目以降を取得してくる
            $aryDataStr = readConfig($confFileName, 2);
            $memberList = setMemberList( $aryDataStr );

            // メンバーリスト分処理を行う
            foreach($memberList as $member) {
                // リストから該当するディレクトリがあるか調べる
                if( $member->setEnabledHitDir($pathDataDir) ) {

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
 * Class Member
 */
class Member {
    private $name;
    private $mail;
    private $dirName;

    /**
     * 渡されたディレクトリパスの中に自分に当たるディレクトリがあれば設定してtrueを返す
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

    /**
     * CSV,TSVで名前とメルアドがつながってるか調べる
     * @param $line 設定ファイルの一行
     * @return bool 読み込めたかどうか
     */
    function setFormatCsvTsv($line) {
        $result = false;

        // csv,tsvになっている
        // メルアドが正しい
        // パスする文字列が入っていない()
        //  ならばset
        //TODO

        return $result;
    }


    /**
     * メールアドレスのフォーマットが正しいか調べる
     * @param $mailaddress
     * @return bool メルアドとして正しいかどうか
     */
    function checkFormatMail($mailaddress) {
        return true;
    }

    function setName($str) { $this->name = $str; }
    function setMail($str) { $this->mail = $str; }
    function setDirName($dirName) { $this->dirName = $dirName; }
    function getName() { return $this->name; }
    function getMail() { return $this->mail; }
    function getDirName() { return $this->dirName; }
}


/**
 * PHP起動時のオプションを取得する
 * @param $argv 起動時のオプション
 */
function getRunOption($argc, $argv) {
    /*

        起動オプションから渡された引数の一つ目で文字列を取得してくる
        スペースが入る場合があるので実行時はダブルコートでくくること

    */
    return '';
}

/**
 * 設定ファイルを開いて読み込
 * @param String $fname 読み込むファイルの名前（パス）
 * @param int $start 読み込む開始位置(最小が1)
 * @param int $num 読み込む回数、指定がない場合0なら最後まで読み込む
 */
function readConfig($fname, $start, $num=0) {
    $result = array();

    print_r(array($fname, $start, $num));

    return array();
}

/**
 * 名前リストからひとつづつ設定して返す
 * @param $aryDataStr
 * @return array
 */
function setMemberList($aryDataStr) {
    $result = array();
    foreach($aryDataStr as $dataStr) {
        $member = new Member();
        if( $member->setFormatCsvTsv($dataStr) ) {
            $result []= $member;
        }
    }

    return $result;
}

/**
 *
 */
function isEnabledDir($path) {
    return false;
}

/**
 * メール送信の確認
 *
 */
function confirmMail($member) {
    return false;
}


/**
 * メール送信
 *
 */
function sendMail($member) {
    return false;
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
}


/**
 * 名前にあたるものが指定されたディレクトリにあるかどうか
 * @param $dirPath 調べるディレクトリ
 * @param $name 調べる名前
 * @return string ヒットしたディレクトリ名
 */
function isEnabledHitDir($dirPath, $name) {
    $result = NULL;

    return $result;
}

