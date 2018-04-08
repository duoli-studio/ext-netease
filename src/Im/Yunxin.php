<?php namespace Poppy\Extension\NetEase\Im;

use System\Classes\Traits\SystemTrait;

class Yunxin
{
	const SEX_MALE   = 1;  //男士
	const SEX_FEMALE = 2;  //女士

	/** @var string 开发者平台分配的AppKey */
	private $appKey;

	/** @var string 开发者平台分配的AppSecret,可刷新 */
	private $appSecret;

	/** @var string 随机数 */
	private $nonce;

	/** @var string 当前UTC时间戳，从1970年1月1日0点0 分0 秒开始到现在的秒数(String) */
	private $curTime;

	/** @var [SHA1](AppSecret + Nonce + CurTime),三个参数拼接的字符串，进行SHA1哈希计算，转化成16进制字符(String，小写) */
	private $checkSum;

	const   HEX_DIGITS = '0123456789abcdef';

	/** @var string 请求方式 */
	private $RequestType;

	use SystemTrait;

	/**
	 * 参数初始化
	 */
	public function __construct()
	{
		$this->appKey      = $this->getSetting()->get('extension::netease_im.app_key');
		$this->appSecret   = $this->getSetting()->get('extension::netease_im.app_secret');
		$this->RequestType = 'curl';
	}

	/**
	 * API  checksum校验生成
	 * @internal param void     $string
	 * @internal param string   $CheckSum (对象私有属性)
	 */
	public function createCheckSum()
	{
		//此部分生成随机字符串
		$hex_digits = self::HEX_DIGITS;
		$this->nonce;
		for ($i = 0; $i < 128; $i++) {              //随机字符串最大128个字符，也可以小于该数
			$this->nonce .= $hex_digits[rand(0, 15)];
		}
		$this->curTime = (string) (time());         //当前时间戳，以秒为单位

		$join_string    = $this->appSecret . $this->nonce . $this->curTime;
		$this->checkSum = sha1($join_string);
	}

	/**
	 * 将json字符串转化成php数组
	 * @param string $json_str
	 * @return array $json_arr
	 */
	public function jsonToArray($json_str)
	{
		// version 1.6 code ...
		// if(is_null(json_decode($json_str))){
		//     $json_str = $json_str;
		// }else{
		//     $json_str = json_decode($json_str);
		// }
		// $json_arr=array();
		// //print_r($json_str);
		// foreach($json_str as $k=>$w){
		//     if(is_object($w)){
		//         $json_arr[$k]= $this->json_to_array($w); //判断类型是不是object
		//     }else if(is_array($w)){
		//         $json_arr[$k]= $this->json_to_array($w);
		//     }else{
		//         $json_arr[$k]= $w;
		//     }
		// }
		// return $json_arr;

		if (is_array($json_str) || is_object($json_str)) {
			$is_json = $json_str;
		}
		elseif (is_null(json_decode($json_str))) {
			$is_json = $json_str;
		}
		else {
			$is_json = strval($json_str);
			$is_json = json_decode($is_json, true);
		}
		$json_arr = [];
		foreach ($is_json as $k => $w) {
			if (is_object($w)) {
				$json_arr[$k] = $this->jsonToArray($w); //判断类型是不是object
			}
			elseif (is_array($w)) {
				$json_arr[$k] = $this->jsonToArray($w);
			}
			else {
				$json_arr[$k] = $w;
			}
		}

		return $json_arr;
	}

	/**
	 * 使用CURL方式发送post请求
	 * @param string $url  [请求地址]
	 * @param array  $data [格式数据]
	 * @return array $请求返回结果(array)
	 */
	public function postDataCurl($url, $data)
	{
		$this->createCheckSum();       //发送请求前需先生成checkSum

		$timeout     = 5000;
		$http_header = [
			'AppKey:' . $this->appKey,
			'Nonce:' . $this->nonce,
			'CurTime:' . $this->curTime,
			'CheckSum:' . $this->checkSum,
			'Content-Type:application/x-www-form-urlencoded;charset=utf-8',
		];
		//print_r($http_header);

		// $postdata = '';
		$postdataArray = [];
		foreach ($data as $key => $value) {
			array_push($postdataArray, $key . '=' . urlencode($value));
			// $postdata.= ($key.'='.urlencode($value).'&');
		}
		$postdata = implode('&', $postdataArray);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //处理http证书问题
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($ch);
		if (false === $result) {
			$result = curl_errno($ch);
		}
		curl_close($ch);

		return $this->jsonToArray($result);
	}

	/**
	 * 使用FSOCKOPEN方式发送post请求
	 * @param  string $url  请求地址
	 * @param  array  $data array格式数据
	 * @return array $请求返回结果(array)
	 */
	public function postDataFsockopen($url, $data)
	{
		$this->createCheckSum();       //发送请求前需先生成checkSum

		// $postdata = '';
		$postdataArray = [];
		foreach ($data as $key => $value) {
			array_push($postdataArray, $key . '=' . urlencode($value));
			// $postdata.= ($key.'='.urlencode($value).'&');
		}
		$postdata = implode('&', $postdataArray);
		// building POST-request:
		$URL_Info = parse_url($url);
		if (!isset($URL_Info['port'])) {
			$URL_Info['port'] = 80;
		}
		$request = '';
		$request .= 'POST ' . $URL_Info['path'] . " HTTP/1.1\r\n";
		$request .= 'Host:' . $URL_Info['host'] . "\r\n";
		$request .= "Content-type: application/x-www-form-urlencoded;charset=utf-8\r\n";
		$request .= 'Content-length: ' . strlen($postdata) . "\r\n";
		$request .= "Connection: close\r\n";
		$request .= 'AppKey: ' . $this->appKey . "\r\n";
		$request .= 'Nonce: ' . $this->nonce . "\r\n";
		$request .= 'CurTime: ' . $this->curTime . "\r\n";
		$request .= 'CheckSum: ' . $this->checkSum . "\r\n";
		$request .= "\r\n";
		$request .= $postdata . "\r\n";

		// print_r($request);
		$fp = fsockopen($URL_Info['host'], $URL_Info['port']);
		fwrite($fp, $request);
		$result = '';
		while (!feof($fp)) {
			$result .= fgets($fp, 128);
		}
		fclose($fp);

		$str_s = strpos($result, '{');
		$str_e = strrpos($result, '}');
		$str   = substr($result, $str_s, $str_e - $str_s + 1);

		return $this->jsonToArray($str);
	}

	/**
	 * 使用CURL方式发送post请求（JSON类型）
	 * @param string $url  [请求地址]
	 * @param array  $data [array格式数据]
	 * @return array $请求返回结果(array)
	 */
	public function postJsonDataCurl($url, $data)
	{
		$this->createCheckSum();        //发送请求前需先生成checkSum

		$timeout     = 5000;
		$http_header = [
			'AppKey:' . $this->appKey,
			'Nonce:' . $this->nonce,
			'CurTime:' . $this->curTime,
			'CheckSum:' . $this->checkSum,
			'Content-Type:application/json;charset=utf-8',
		];

		$postdata = json_encode($data);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //处理http证书问题
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($ch);
		if (false === $result) {
			$result = curl_errno($ch);
		}
		curl_close($ch);

		return $this->jsonToArray($result);
	}

	/**
	 * 使用FSOCKOPEN方式发送post请求（json）
	 * @param string $url  [请求地址]
	 * @param array  $data [array格式数据]
	 * @return array $请求返回结果(array)
	 */
	public function postJsonDataFsockopen($url, $data)
	{
		$this->createCheckSum();       //发送请求前需先生成checkSum

		$postdata = json_encode($data);

		// building POST-request:
		$URL_Info = parse_url($url);
		if (!isset($URL_Info['port'])) {
			$URL_Info['port'] = 80;
		}
		$request = '';
		$request .= 'POST ' . $URL_Info['path'] . " HTTP/1.1\r\n";
		$request .= 'Host:' . $URL_Info['host'] . "\r\n";
		$request .= "Content-type: application/json;charset=utf-8\r\n";
		$request .= 'Content-length: ' . strlen($postdata) . "\r\n";
		$request .= "Connection: close\r\n";
		$request .= 'AppKey: ' . $this->appKey . "\r\n";
		$request .= 'Nonce: ' . $this->nonce . "\r\n";
		$request .= 'CurTime: ' . $this->curTime . "\r\n";
		$request .= 'CheckSum: ' . $this->checkSum . "\r\n";
		$request .= "\r\n";
		$request .= $postdata . "\r\n";

		print_r($request);
		$fp = fsockopen($URL_Info['host'], $URL_Info['port']);
		fwrite($fp, $request);
		$result = '';
		while (!feof($fp)) {
			$result .= fgets($fp, 128);
		}
		fclose($fp);

		$str_s = strpos($result, '{');
		$str_e = strrpos($result, '}');
		$str   = substr($result, $str_s, $str_e - $str_s + 1);

		return $this->jsonToArray($str);
	}

	/**
	 * 创建云信ID
	 * 1.第三方帐号导入到云信平台；
	 * 2.注意accid，name长度以及考虑管理秘钥token
	 * @param $data
	 * @return array $result    [返回array数组对象]
	 */
	public function createUserIds($data)
	{
		$url = 'https://api.netease.im/nimserver/user/create.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 更新云信ID
	 * @param array $data
	 * @return array $result [返回array数组对象]
	 */
	public function updateUserId($data)
	{
		$url = 'https://api.netease.im/nimserver/user/update.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 更新并获取新token
	 * @param  array $data [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @return array $result  [返回array数组对象]
	 */
	public function updateUserToken($data)
	{
		$url = 'https://api.netease.im/nimserver/user/refreshToken.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 封禁云信ID
	 * 第三方禁用某个云信ID的IM功能,封禁云信ID后，此ID将不能登陆云信imserver
	 * @param string $accid [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @return array $result  [返回array数组对象]
	 */
	public function blockUserId($accid)
	{
		$url  = 'https://api.netease.im/nimserver/user/block.action';
		$data = [
			'accid' => $accid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 解禁云信ID  第三方禁用某个云信ID的IM功能,封禁云信ID后，此ID将不能登陆云信imserver
	 * @param string $accid [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @return array $result  [返回array数组对象]
	 */
	public function unblockUserId($accid)
	{
		$url  = 'https://api.netease.im/nimserver/user/unblock.action';
		$data = [
			'accid' => $accid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 更新用户名片
	 * @param string     $accid  [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @param string     $name   [云信ID昵称，最大长度64字节，用来PUSH推送时显示的昵称]
	 * @param string     $icon   [用户icon，最大长度256字节]
	 * @param string     $sign   [用户签名，最大长度256字节]
	 * @param string     $email  [用户email，最大长度64字节]
	 * @param string     $birth  [用户生日，最大长度16字节]
	 * @param string     $mobile [用户mobile，最大长度32字节]
	 * @param int|string $gender [用户性别，0表示未知，1表示男，2女表示女，其它会报参数错误]
	 * @param string     $ex     [用户名片扩展字段，最大长度1024字节，用户可自行扩展，建议封装成JSON字符串]
	 * @return array $result      [返回array数组对象]
	 */
	public function updateUserInfo($data)
	{
		$url = 'https://api.netease.im/nimserver/user/updateUinfo.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 获取用户名片，可批量
	 * @param array $accids [用户帐号（例如：JSONArray对应的accid串，如："zhangsan"，如果解析出错，会报414）（一次查询最多为200）]
	 * @return array $result  [返回array数组对象]
	 */
	public function getUserInfos($accids)
	{
		$url  = 'https://api.netease.im/nimserver/user/getUinfos.action';
		$data = [
			'accids' => json_encode($accids),
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 好友关系-加好友
	 * @param string $accid  [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @param string $faccid [要加的好友id]
	 * @param string $type   [用户type，最大长度256字节]
	 * @param string $msg    [用户签名，最大长度256字节]
	 * @return array $result      [返回array数组对象]
	 */
	public function addFriend($accid, $faccid, $type = '1', $msg = '')
	{
		$url  = 'https://api.netease.im/nimserver/friend/add.action';
		$data = [
			'accid'  => $accid,
			'faccid' => $faccid,
			'type'   => $type,
			'msg'    => $msg,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 好友关系-更新好友信息
	 * @param string $accid  [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @param string $faccid [要修改朋友的accid]
	 * @param string $alias  [给好友增加备注名]
	 * @return array $result      [返回array数组对象]
	 */
	public function updateFriend($accid, $faccid, $alias)
	{
		$url  = 'https://api.netease.im/nimserver/friend/update.action';
		$data = [
			'accid'  => $accid,
			'faccid' => $faccid,
			'alias'  => $alias,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 好友关系-获取好友关系
	 * @param string $accid [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @return array $result   [返回array数组对象]
	 */
	public function getFriend($accid)
	{
		$url  = 'https://api.netease.im/nimserver/friend/get.action';
		$data = [
			'accid'      => $accid,
			'createtime' => (string) (time() * 1000),
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 好友关系-删除好友信息
	 * @param string $accid  [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @param string $faccid [要修改朋友的accid]
	 * @return array $result      [返回array数组对象]
	 */
	public function deleteFriend($accid, $faccid)
	{
		$url  = 'https://api.netease.im/nimserver/friend/delete.action';
		$data = [
			'accid'  => $accid,
			'faccid' => $faccid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 好友关系-设置黑名单、静音用户
	 * @param string     $accid        [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @param string     $targetAcc    [被加黑或加静音的帐号]
	 * @param int|string $relationType [本次操作的关系类型,1:黑名单操作，2:静音列表操作]
	 * @param int|string $value        [操作值，0:取消黑名单或静音；1:加入黑名单或静音]
	 * @return array $result        [返回array数组对象]
	 */
	public function blackFriend($accid, $targetAcc, $relationType = '1', $value = '1')
	{
		$url  = 'https://api.netease.im/nimserver/user/setSpecialRelation.action';
		$data = [
			'accid'        => $accid,
			'targetAcc'    => $targetAcc,
			'relationType' => $relationType,
			'value'        => $value,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 好友关系-查看黑名单列表
	 * @param string $accid [云信ID，最大长度32字节，必须保证一个APP内唯一（只允许字母、数字、半角下划线_、@、半角点以及半角-组成，不区分大小写，会统一小写处理）]
	 * @return array $result  [返回array数组对象]
	 */
	public function listBlackFriend($accid)
	{
		$url  = 'https://api.netease.im/nimserver/user/listBlackAndMuteList.action';
		$data = [
			'accid' => $accid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 消息功能-发送普通消息
	 * @param array $data
	 * @return array $result       [返回array数组对象]
	 */
	public function sendMsg($data)
	{
		$url = 'https://api.netease.im/nimserver/msg/sendMsg.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	public function sendBatchMsg($data)
	{
		$url = 'https://api.netease.im/nimserver/msg/sendBatchMsg.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 消息功能-发送自定义系统消息  1.自定义系统通知区别于普通消息，方便开发者进行业务逻辑的通知。 2.目前支持两种类型：点对点类型和群类型（仅限高级群），根据msgType有所区别。
	 * @param  array $data
	 * @return array $result            [返回array数组对象]
	 */
	public function sendAttachMsg($data)
	{
		$url = 'https://api.netease.im/nimserver/msg/sendAttachMsg.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 消息功能-文件上传
	 * @param string $content [字节流base64串(Base64.encode(bytes)) ，最大15M的字节流]
	 * @param string $type    [上传文件类型]
	 * @return array $result  [返回array数组对象]
	 */
	public function uploadMsg($content, $type = '0')
	{
		$url  = 'https://api.netease.im/nimserver/msg/upload.action';
		$data = [
			'content' => $content,
			'type'    => $type,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 消息功能-文件上传（multipart方式）
	 * @param string     $content [字节流base64串(Base64.encode(bytes)) ，最大15M的字节流]
	 * @param int|string $type    [上传文件类型]
	 * @return array     $result  [返回array数组对象]
	 */
	public function uploadMultiMsg($content, $type = '0')
	{
		$url  = 'https://api.netease.im/nimserver/msg/fileUpload.action';
		$data = [
			'content' => $content,
			'type'    => $type,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-创建群
	 * @param string     $tname        [群名称，最大长度64字节]
	 * @param string     $owner        [群主用户帐号，最大长度32字节]
	 * @param string     $members      [["aaa","bbb"](JsonArray对应的accid，如果解析出错会报414)，长度最大1024字节]
	 * @param string     $announcement [群公告，最大长度1024字节]
	 * @param string     $intro        [群描述，最大长度512字节]
	 * @param string     $msg          [邀请发送的文字，最大长度150字节]
	 * @param int|string $magree       [管理后台建群时，0不需要被邀请人同意加入群，1需要被邀请人同意才可以加入群。其它会返回414。]
	 * @param int|string $joinmode     [群建好后，sdk操作时，0不用验证，1需要验证,2不允许任何人加入。其它返回414]
	 * @param string     $custom       [自定义高级群扩展属性，第三方可以跟据此属性自定义扩展自己的群属性。（建议为json）,最大长度1024字节.]
	 * @return array $result           [返回array数组对象]
	 */
	// public function createGroup($tname, $owner, $members, $announcement = '', $intro = '', $msg = '', $magree = '0', $joinmode = '0', $custom = '0')
	public function createGroup($data)
	{
		$url = 'https://api.netease.im/nimserver/team/create.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-拉人入群
	 * @param           $tid     [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
	 * @param           $owner   [群主用户帐号，最大长度32字节]
	 * @param           $members [["aaa","bbb"](JsonArray对应的accid，如果解析出错会报414)，长度最大1024字节]
	 * @param string    $magree  [管理后台建群时，0不需要被邀请人同意加入群，1需要被邀请人同意才可以加入群。其它会返回414。]
	 * @param string    $msg     [邀请入群提示语]
	 * @return array    $result    [返回array数组对象]
	 * @internal param  $joinmode  [群建好后，sdk操作时，0不用验证，1需要验证,2不允许任何人加入。其它返回414]
	 * @internal param  $custom    [自定义高级群扩展属性，第三方可以跟据此属性自定义扩展自己的群属性。（建议为json）,最大长度1024字节.]
	 */
	public function addIntoGroup($tid, $owner, $members, $magree = '0', $msg = '请您入伙')
	{
		$url  = 'https://api.netease.im/nimserver/team/add.action';
		$data = [
			'tid'     => $tid,
			'owner'   => $owner,
			'members' => json_encode($members),
			'magree'  => $magree,
			'msg'     => $msg,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-踢人出群
	 * @param string $tid    [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
	 * @param string $owner  [群主用户帐号，最大长度32字节]
	 * @param string $member [被移除人得acc_id，用户账号，最大长度字节]
	 * @return array $result [返回array数组对象]
	 */
	public function kickFromGroup($tid, $owner, $member)
	{
		$url  = 'https://api.netease.im/nimserver/team/kick.action';
		$data = [
			'tid'    => $tid,
			'owner'  => $owner,
			'member' => $member,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-解散群
	 * @param string $tid   [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
	 * @param string $owner [群主用户帐号，最大长度32字节]
	 * @return array $result      [返回array数组对象]
	 */
	public function removeGroup($data)
	{
		$url  = 'https://api.netease.im/nimserver/team/remove.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-更新群资料
	 * @param string $tid          [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
	 * @param string $owner        [群主用户帐号，最大长度32字节]
	 * @param string $tname        [群主用户帐号，最大长度32字节]
	 * @param string $announcement [群公告，最大长度1024字节]
	 * @param string $intro        [群描述，最大长度512字节]
	 * @param string $joinmode     [群建好后，sdk操作时，0不用验证，1需要验证,2不允许任何人加入。其它返回414]
	 * @param string $custom       [自定义高级群扩展属性，第三方可以跟据此属性自定义扩展自己的群属性。（建议为json）,最大长度1024字节.]
	 * @return array $result       [返回array数组对象]
	 */
	public function updateGroup($tid, $owner, $tname, $announcement = '', $intro = '', $joinmode = '0', $custom = '')
	{
		$url  = 'https://api.netease.im/nimserver/team/update.action';
		$data = [
			'tid'          => $tid,
			'owner'        => $owner,
			'tname'        => $tname,
			'announcement' => $announcement,
			'intro'        => $intro,
			'joinmode'     => $joinmode,
			'custom'       => $custom,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-群信息与成员列表查询
	 * @param string $tids [群tid列表，如[\"3083\",\"3084"]]
	 * @param string $ope  [1表示带上群成员列表，0表示不带群成员列表，只返回群信息]
	 * @return array  $result [返回array数组对象]
	 */
	public function queryGroup($tids, $ope = '1')
	{
		$url  = 'https://api.netease.im/nimserver/team/query.action';
		$data = [
			'tids' => json_encode($tids),
			'ope'  => $ope,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}


	public function queryDetail($data)
	{
		$url  = 'https://api.netease.im/nimserver/team/queryDetail.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-移交群主
	 * @param string $tid      [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
	 * @param string $owner    [群主用户帐号，最大长度32字节]
	 * @param string $newowner [新群主帐号，最大长度32字节]
	 * @param string $leave    [1:群主解除群主后离开群，2：群主解除群主后成为普通成员。其它414]
	 * @return array $result   [返回array数组对象]
	 */
	public function changeGroupOwner($tid, $owner, $newowner, $leave = '2')
	{
		$url  = 'https://api.netease.im/nimserver/team/changeOwner.action';
		$data = [
			'tid'      => $tid,
			'owner'    => $owner,
			'newowner' => $newowner,
			'leave'    => $leave,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-任命管理员
	 * @param string $tid     [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
	 * @param string $owner   [群主用户帐号，最大长度32字节]
	 * @param string $members [["aaa","bbb"](JsonArray对应的accid，如果解析出错会报414)，长度最大1024字节（群成员最多10个）]
	 * @return array $result  [返回array数组对象]
	 */
	public function addGroupManager($tid, $owner, $members)
	{
		$url  = 'https://api.netease.im/nimserver/team/addManager.action';
		$data = [
			'tid'     => $tid,
			'owner'   => $owner,
			'members' => json_encode($members),
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-移除管理员
	 * @param string $tid     [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
	 * @param string $owner   [群主用户帐号，最大长度32字节]
	 * @param string $members [["aaa","bbb"](JsonArray对应的accid，如果解析出错会报414)，长度最大1024字节（群成员最多10个）]
	 * @return array $result  [返回array数组对象]
	 */
	public function removeGroupManager($tid, $owner, $members)
	{
		$url  = 'https://api.netease.im/nimserver/team/removeManager.action';
		$data = [
			'tid'     => $tid,
			'owner'   => $owner,
			'members' => json_encode($members),
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-获取某用户所加入的群信息
	 * @param string $accid [要查询用户的accid]
	 * @return array $result [返回array数组对象]
	 */
	public function joinTeams($accid)
	{
		$url  = 'https://api.netease.im/nimserver/team/joinTeams.action';
		$data = [
			'accid' => $accid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 群组功能（高级群）-修改群昵称
	 * @param string $tid   [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
	 * @param string $owner [群主用户帐号，最大长度32字节]
	 * @param string $accid [要修改群昵称对应群成员的accid]
	 * @param string $nick  [accid对应的群昵称，最大长度32字节。]
	 * @return array $result      [返回array数组对象]
	 */
	public function updateGroupNick($tid, $owner, $accid, $nick)
	{
		$url  = 'https://api.netease.im/nimserver/team/updateTeamNick.action';
		$data = [
			'tid'   => $tid,
			'owner' => $owner,
			'accid' => $accid,
			'nick'  => $nick,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 历史记录-单聊
	 * @param string $from      [发送者accid]
	 * @param string $to        [接收者accid]
	 * @param string $begintime [开始时间，ms]
	 * @param string $endtime   [截止时间，ms]
	 * @param string $limit     [本次查询的消息条数上限(最多100条),小于等于0，或者大于100，会提示参数错误]
	 * @param string $reverse   [1按时间正序排列，2按时间降序排列。其它返回参数414.默认是按降序排列。]
	 * @return array $result      [返回array数组对象]
	 */
	public function querySessionMsg($data)
	{
		$url  = 'https://api.netease.im/nimserver/history/querySessionMsg.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 历史记录-群聊
	 * @param string $tid       [群id]
	 * @param string $accid     [查询用户对应的accid.]
	 * @param string $begintime [开始时间，ms]
	 * @param string $endtime   [截止时间，ms]
	 * @param string $limit     [本次查询的消息条数上限(最多100条),小于等于0，或者大于100，会提示参数错误]
	 * @param string $reverse   [1按时间正序排列，2按时间降序排列。其它返回参数414.默认是按降序排列。]
	 * @return array $result      [返回array数组对象]
	 */
	public function queryGroupMsg($tid, $accid, $begintime, $endtime = '', $limit = '100', $reverse = '1')
	{
		$url  = 'https://api.netease.im/nimserver/history/queryTeamMsg.action';
		$data = [
			'tid'       => $tid,
			'accid'     => $accid,
			'begintime' => $begintime,
			'endtime'   => $endtime,
			'limit'     => $limit,
			'reverse'   => $reverse,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 发送短信验证码
	 * @param string $mobile   [目标手机号]
	 * @param string $deviceId [目标设备号，可选参数]
	 * @return array $result   [返回array数组对象]
	 */
	public function sendSmsCode($mobile, $deviceId = '')
	{
		$url  = 'https://api.netease.im/sms/sendcode.action';
		$data = [
			'mobile'   => $mobile,
			'deviceId' => $deviceId,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 校验验证码
	 * @param string $mobile [目标手机号]
	 * @param string $code   [验证码]
	 * @return array $result      [返回array数组对象]
	 */
	public function verifyCode($mobile, $code = '')
	{
		$url  = 'https://api.netease.im/sms/verifycode.action';
		$data = [
			'mobile' => $mobile,
			'code'   => $code,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 发送模板短信
	 * @param array $templateid [模板编号(由客服配置之后告知开发者)]
	 * @param array $mobiles    [验证码]
	 * @param array $params     [短信参数列表，用于依次填充模板，JSONArray格式，如["xxx","yyy"];对于不包含变量的模板，不填此参数表示模板即短信全文内容]
	 * @return array $result      [返回array数组对象]
	 */
	public function sendSMSTemplate($templateid, $mobiles = [], $params = [])
	{
		$url  = 'https://api.netease.im/sms/sendtemplate.action';
		$data = [
			'templateid' => $templateid,
			'mobiles'    => json_encode($mobiles),
			'params'     => json_encode($params),
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 查询模板短信发送状态
	 * @param string $sendid [发送短信的编号sendid]
	 * @return array $result [返回array数组对象]
	 */
	public function querySMSStatus($sendid)
	{
		$url  = 'https://api.netease.im/sms/querystatus.action';
		$data = [
			'sendid' => $sendid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 发起单人专线电话
	 * @param string $callerAcc [发起本次请求的用户的accid]
	 * @param string $caller    [主叫方电话号码(不带+86这类国家码,下同)]
	 * @param string $callee    [被叫方电话号码]
	 * @param string $maxDur    [本通电话最大可持续时长,单位秒,超过该时长时通话会自动切断]
	 * @return array $result      [返回array数组对象]
	 */
	public function startCall($callerAcc, $caller, $callee, $maxDur = '60')
	{
		$url  = 'https://api.netease.im/call/ecp/startcall.action';
		$data = [
			'callerAcc' => $callerAcc,
			'caller'    => $caller,
			'callee'    => $callee,
			'maxDur'    => $maxDur,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 发起专线会议电话
	 * @param string $callerAcc [发起本次请求的用户的accid]
	 * @param string $caller    [主叫方电话号码(不带+86这类国家码,下同)]
	 * @param string $callee    [所有被叫方电话号码,必须是json格式的字符串,如["13588888888","13699999999"]]
	 * @param string $maxDur    [本通电话最大可持续时长,单位秒,超过该时长时通话会自动切断]
	 * @return array $result      [返回array数组对象]
	 */
	public function startConf($callerAcc, $caller, $callee, $maxDur = '60')
	{
		$url  = 'https://api.netease.im/call/ecp/startconf.action';
		$data = [
			'callerAcc' => $callerAcc,
			'caller'    => $caller,
			'callee'    => json_encode($callee),
			'maxDur'    => $maxDur,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 查询单通专线电话或会议的详情
	 * @param string $session [本次通话的id号]
	 * @param string $type    [通话类型,1:专线电话;2:专线会议]
	 * @return array $result      [返回array数组对象]
	 */
	public function queryCallsBySession($session, $type)
	{
		$url  = 'https://api.netease.im/call/ecp/queryBySession.action';
		$data = [
			'session' => $session,
			'type'    => $type,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/* 2016-06-15 新增php调用直播接口示例 */

	/**
	 * 获取语音视频安全认证签名
	 * @param string $uid [用户帐号唯一标识，必须是Long型]
	 * @return array
	 */
	public function getUserSignature($uid)
	{
		$url  = 'https://api.netease.im/nimserver/user/getToken.action';
		$data = [
			'uid' => $uid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 创建一个直播频道
	 * @param string  $name [频道名称, string]
	 * @param int $type [频道类型（0:rtmp；1:hls；2:http）]
	 * @return array
	 */
	public function channelCreate($name, $type)
	{
		$url  = 'https://vcloud.163.com/app/channel/create';
		$data = [
			'name' => $name,
			'type' => $type,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postJsonDataCurl($url, $data);
		}
		else {
			$result = $this->postJsonDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 修改直播频道信息
	 * @param string  $name [频道名称, string]
	 * @param string  $cid  [频道ID，32位字符串]
	 * @param int $type [频道类型（0:rtmp；1:hls；2:http）]
	 * @return array
	 */
	public function channelUpdate($name, $cid, $type)
	{
		$url  = 'https://vcloud.163.com/app/channel/update';
		$data = [
			'name' => $name,
			'cid'  => $cid,
			'type' => $type,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postJsonDataCurl($url, $data);
		}
		else {
			$result = $this->postJsonDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 删除一个直播频道
	 * @param string $cid [频道ID，32位字符串]
	 * @return array
	 */
	public function channelDelete($cid)
	{
		$url  = 'https://vcloud.163.com/app/channel/delete';
		$data = [
			'cid' => $cid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postJsonDataCurl($url, $data);
		}
		else {
			$result = $this->postJsonDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 获取一个直播频道的信息
	 * @param string $cid [频道ID，32位字符串]
	 * @return array
	 */
	public function channelStats($cid)
	{
		$url  = 'https://vcloud.163.com/app/channelstats';
		$data = [
			'cid' => $cid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postJsonDataCurl($url, $data);
		}
		else {
			$result = $this->postJsonDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 获取用户直播频道列表
	 * @para  integer  $records [单页记录数，默认值为10]
	 * @param int     $records
	 * @param int $pnum   = 1       [要取第几页，默认值为1]
	 * @param string  $ofield [排序的域，支持的排序域为：ctime（默认）]
	 * @param int $sort   [升序还是降序，1升序，0降序，默认为desc]
	 * @return array
	 */
	public function channelList($records = 10, $pnum = 1, $ofield = 'ctime', $sort = 0)
	{
		$url  = 'https://vcloud.163.com/app/channellist';
		$data = [
			'records' => $records,
			'pnum'    => $pnum,
			'ofield'  => $ofield,
			'sort'    => $sort,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postJsonDataCurl($url, $data);
		}
		else {
			$result = $this->postJsonDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 重新获取推流地址
	 * @param string $cid [频道ID，32位字符串]
	 * @return array
	 */
	public function channelRefreshAddr($cid)
	{
		$url  = 'https://vcloud.163.com/app/address';
		$data = [
			'cid' => $cid,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postJsonDataCurl($url, $data);
		}
		else {
			$result = $this->postJsonDataFsockopen($url, $data);
		}

		return $result;
	}

	// 2015-07-04 聊天室功能开发 gm

	/**
	 * 创建聊天室
	 * @param array $data
	 * @return array
	 */
	public function chatRoomCreates($data)
	{
		$url = 'https://api.netease.im/nimserver/chatroom/create.action';
		// $data = [
		// 	'creator' => $accid,
		// 	'name'    => $name,
		// ];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 更新聊天室
	 * @param $roomid [聊天室ID]
	 * @param $name   [聊天室名称]
	 * @return array
	 */
	public function chatRoomUpdates($roomid, $name)
	{
		$url  = 'https://api.netease.im/nimserver/chatroom/update.action';
		$data = [
			'roomid' => $roomid,
			'name'   => $name,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 修改 关闭聊天室
	 * @param  int $roomid   [聊天室ID]
	 * @param  string  $operator [创建者ID]
	 * @internal param string $status 修改还是关闭  false => 关闭
	 * @return array
	 */
	public function chatRoomToggleCloses($roomid, $operator)
	{
		$url  = 'https://api.netease.im/nimserver/chatroom/toggleCloseStat.action';
		$data = [
			'roomid'   => $roomid,
			'operator' => $operator,
			'valid'    => 'false',
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 修改 开启聊天室
	 * @param string $roomid
	 * @param string $operator
	 * @return array
	 */
	public function chatRoomToggleStats($roomid, $operator)
	{
		$url  = 'https://api.netease.im/nimserver/chatroom/toggleCloseStat.action';
		$data = [
			'roomid'   => $roomid,
			'operator' => $operator,
			'valid'    => 'true',
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 *设置聊天室内用户角色
	 * @param   string  $roomid   // 聊天室ID
	 * @param   string  $operator // 操作者账号accid   operator必须是创建者
	 * @param   string  $target   // 被操作者账号accid
	 * @param   int $opt
	 *                            1: 设置为管理员，operator必须是创建者
	 *                            2:设置普通等级用户，operator必须是创建者或管理员
	 *                            -1:设为黑名单用户，operator必须是创建者或管理员
	 *                            -2:设为禁言用户，operator必须是创建者或管理员
	 * @param string    $optvalue // true:设置；false:取消设置
	 * @return array
	 */
	public function chatRoomSetMemberRoles($roomid, $operator, $target, $opt, $optvalue)
	{
		$url  = 'https://api.netease.im/nimserver/chatroom/setMemberRole.action';
		$data = [
			'roomid'   => $roomid,
			'operator' => $operator,
			'target'   => $target,
			'opt'      => $opt,
			'optvalue' => $optvalue,
		];
		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}

	/**
	 * 获取聊天室的信息
	 * @param $data
	 * @return array
	 */
	public function chatRoomGets($data)
	{
		$url = 'https://api.netease.im/nimserver/chatroom/get.action';

		if ($this->RequestType == 'curl') {
			$result = $this->postDataCurl($url, $data);
		}
		else {
			$result = $this->postDataFsockopen($url, $data);
		}

		return $result;
	}
}