<?php namespace Poppy\Extension\Netease\Im\Tests;

use Carbon\Carbon;
use Poppy\Extension\NetEase\Im\Yunxin;
use Poppy\Framework\Application\TestCase;
use System\Models\PamAccount;

class ImTest extends TestCase
{
	public function testImCreateAccId()
	{
		$yunxin = new Yunxin();

		$accid = 'fadan001';

		$pam = PamAccount::where('username', $accid)->first();

		//注册时间 用户id
		// $token = md5('HH_[' . $pam->id . '][' . date('Y-m-d H:i:s', time()) . ']');

		$prefix = 'GO_';
		$token  = md5($prefix . '_[' . 195 . '][' . date('Y-m-d H:i:s', time()) . ']');
		$accid  = '环境 + | + 用户名 + id';
		$data   = [
			'accid' => 'local_000000300',
			// 'token' => $token b8b2d9809b3d06d3e418571685f8f960   手动：23e76484d857fc8125e8a2bae2ba67c9
		];
		$result = $yunxin->createUserIds($data);
		if ($result['code'] == 200) {
			dd($result);
		}
		else {
			dd($result);
		}
	}

	/**
	 * 获取新的token
	 */
	public function testGetNewToken()
	{
		$yun    = new Yunxin();
		$data   = [
			'accid' => 'dev_000000003',
		];
		$result = $yun->updateUserToken($data);

		if ($result['code'] == 200) {
			dd($result);
		}
	}

	/**
	 * array:2 [
	 * "code" => 200
	 * "uinfos" => array:2 [
	 * 0 => array:4 [
	 * "accid" => "hh_000000122"
	 * "name" => "二丫"
	 * "gender" => 2
	 * "mobile" => "18663298819"
	 * ]
	 * 1 => array:4 [
	 * "icon" => "20b85d15a072f03774572a27b024a7ad"
	 * "accid" => "fadan001"
	 * "name" => "二丫"
	 * "gender" => 0
	 * ]
	 * ]
	 * ]
	 * @return bool
	 */
	public function testGetUserInfos()
	{
		$yun = new Yunxin();

		$data = ['dev_000000003'];

		foreach ($data as $v) {
			$a[] = $v;
		}
		if (!$result = $yun->getUserInfos($a)) {
			return false;
		}

		dd($result);
	}

	/**
	 * 创建聊天室返回 需要控制台开启
	 * array:2 [
	 * "chatroom" => array:9 [
	 * "roomid" => 21425331
	 * "valid" => true
	 * "announcement" => "This is ok"
	 * "queuelevel" => 0
	 * "muted" => false
	 * "name" => "Let us go to car for fly"
	 * "broadcasturl" => "http://www.cqiu.top/mv/2312312"
	 * "ext" => ""
	 * "creator" => "go_000000171"
	 * ]
	 *  "code" => 200
	 * ]
	 * @return bool
	 */
	public function testCreateChat()
	{
		$yun  = new Yunxin();
		$data = [
			'creator'      => 'liexiang',
			'name'         => 'winner winner chicken dinner!',
			'announcement' => 'This is ok',
			'broadcasturl' => 'http://www.cqiu.top',
			'ext'          => '',
			'queuelevel'   => 0,
		];
		if (!$result = $this->yun->chatRoomCreates($data)) {
			return false;
		}

		dd($result);
	}

	/**
	 * 拉人进群
	 */
	public function testPullMan()
	{
		$yun  = new Yunxin();
		$data = [
			'tid' => '',
		];
	}

	/**
	 * 查询聊天室信息
	 * needOnlineUserCount => true 则是查看在线人数
	 */
	public function testGetChatRoom()
	{
		$yun    = new Yunxin();
		$data   = [
			'roomid'              => '21730164',
			'needOnlineUserCount' => true,
		];
		$result = $yun->chatRoomGets($data);
		dd($result);
	}

	/**
	 * 创建一个群
	 *  array:2 [
	 * "code" => 200
	 * "tid" => "302393585"
	 * ]
	 * 16 16 41 398842477
	 * 09 33 03 400121800
	 */
	public function testCreateSpace()
	{
		$yun          = new Yunxin();
		$members      = [
			'local_000000402',
			'local_000000411',
			'local_000000404',
			'local_000000419',
			'local_000000413',
			'go_000000176'
		];
		$announcement = '大家好，我是大猫';
		$intro        = 'zhe shi yi ge di qiu';
		$msg          = '肆无忌惮';
		$magree       = 0;
		$joinmode     = 0;
		$custom       = '';
		$data         = [
			'tname'        => '猎象开黑小分队',
			'owner'        => 'fadan001',
			'members'      => json_encode($members),
			'announcement' => $announcement,
			'intro'        => $intro,
			'msg'          => $msg,
			'magree'       => $magree,
			'joinmode'     => $joinmode,
			'custom'       => $custom,
		];
		$result       = $yun->createGroup($data);
		dd($result);
	}

	/**
	 * 获取群组信息
	 * array:2 [
	 * "code" => 200
	 * "tinfo" => array:16 [
	 * "icon" => null
	 * "announcement" => null
	 * "muteType" => 0
	 * "uptinfomode" => 0
	 * "maxusers" => 200
	 * "admins" => []
	 * "intro" => null
	 * "upcustommode" => 0
	 * "owner" => array:6 [
	 * "createtime" => 1522290763655
	 * "updatetime" => 1522290763655
	 * "nick" => ""
	 * "accid" => "ddboss"
	 * "mute" => false
	 * "custom" => null
	 * ]
	 * "tname" => "when you fail"
	 * "beinvitemode" => 0
	 * "joinmode" => 0
	 * "tid" => 398352259
	 * "members" => array:2 [
	 * 0 => array:6 [
	 * "createtime" => 1522290763655
	 * "updatetime" => 1522290763655
	 * "nick" => ""
	 * "accid" => "go_000000176"
	 * "mute" => false
	 * "custom" => null
	 * ]
	 * 1 => array:6 [
	 * "createtime" => 1522290763655
	 * "updatetime" => 1522290763655
	 * "nick" => ""
	 * "accid" => "liexiang"
	 * "mute" => false
	 * "custom" => null
	 * ]
	 * ]
	 * "invitemode" => 0
	 * "mute" => false
	 * ]
	 * ]
	 */
	public function testGetSpaceInfo()
	{
		$yun    = new Yunxin();
		$data   = [
			'tid' => 398842477,
		];
		$result = $yun->queryDetail($data);
		dd($result);
	}

	/**
	 * 获取某一个用户加入的所有群的信息
	 * apiSuccess @param array
	 *            array:3 [
	 *            "infos" => array:5 [
	 *            0 => array:5 [
	 *            "owner" => "ddboss"
	 *            "tname" => "when you fail"
	 *            "maxusers" => 200
	 *            "tid" => 398352259
	 *            "size" => 3
	 *            ]
	 *            1 => array:5 [
	 *            "owner" => "go_000000176"
	 *            "tname" => "猎象开黑小分队"
	 *            "maxusers" => 200
	 *            "tid" => 302393585
	 *            "size" => 2
	 *            ]
	 *            2 => array:5 [
	 *            "owner" => "fadan001"
	 *            "tname" => "猎象开黑小分队"
	 *            "maxusers" => 200
	 *            "tid" => 373542465
	 *            "size" => 2
	 *            ]
	 *            3 => array:5 [
	 *            "owner" => "fadan001"
	 *            "tname" => "猎象开黑小分队"
	 *            "maxusers" => 200
	 *            "tid" => 398380195
	 *            "size" => 2
	 *            ]
	 *            4 => array:5 [
	 *            "owner" => "ddboss"
	 *            "tname" => "when you fail"
	 *            "maxusers" => 200
	 *            "tid" => 398361911
	 *            "size" => 3
	 *            ]
	 *            ]
	 *            "count" => 5
	 *            "code" => 200
	 *            ]
	 */
	public function testGetGrops()
	{
		$yun    = new Yunxin();
		$result = $yun->joinTeams('ddboss');
		dd($result);
	}

	/**
	 * 解散一个群
	 */
	public function testRemoveGroups()
	{
		$yun = new Yunxin();
		$data = [
			'tid' => '302393585',
			'owner' => 'go_000000176',
		];
		$result = $yun->removeGroup($data);
		dd($result);
	}

	/**
	 * 获取用户消息
	 * array:2 [
	 *     "code" => 200
	 * "uinfos" => array:1 [
	 * 0 => array:4 [
	 * "icon"   => "20b85d15a072f03774572a27b024a7ad"
	 * "accid"  => "fadan001"
	 * "name"   => "二丫"
	 * "gender" => 0
	 * ]
	 * ]
	 * ]
	 * @return bool
	 */
	public function testGetUserinfo()
	{
		$yun = new Yunxin();

		$result = $yun->getUserInfos(['ddboss', 'idailian', 'liexiang', 'cqqqq']);
		if ($result) {
			dd($result);
			return true;
		}
		else {
			dd($result);
			return false;
		}
	}

	/**
	 * 更新用户信息
	 * array:1 [
	 *  "code" => 2
	 * ]
	 */
	public function testUpdateUserId()
	{
		$yun    = new Yunxin();
		$token  = md5('HH_[' . 3 . '][' . date('Y-m-d H:i:s', time()) . ']');
		$data   = [
			'accid' => 'go_000000248',
			'props' => '',
			'token' => $token,
		];
		$result = $yun->updateUserId($data);
		if ($result) {
			\Log::debug($token);
			dd($result);
		}
	}

	/**
	 * 发送普通消息
	 * array:1 [
	 * "code" => 200
	 * ]
	 * @return bool
	 */
	public function testSendImageMsg()
	{
		$yun    = new Yunxin();
		$data   = [
			'from' => 'ddboss',
			'ope'  => 0,
			'to'   => 'go_000000176',
			'type' => 1,
			'body' => '{
				"name":"青歌送给世界的礼物",
				"md5":"' . md5(123123) . '",
				"url":"http://1dailian-test.oss-cn-qingdao.aliyuncs.com/default/201803/13/09/4101m73e2Lj0.png",
				"ext":"png",
				"msg":"999999999皮炎平",
				"w":"480",
				"h":"480",
				"size":"213124",
			}',
		];
		$result = $yun->sendMsg($data);
		dd($result);
		if ($result['code'] != 200) {

			return false;
		}
		dd($result);
	}

	public function testSendMsg()
	{
		$yun    = new Yunxin();
		$Msg    = [
			'from' => 'ddboss',
			'ope'  => 0,
			'type' => 100,
			'to'   => 'go_000000204',
			'body' => json_encode([
				'data' => [
					'action'       => 'can_rob',
					'is_can_click' => true,
					'title'        => '天外来单',
					'content'      => '有一个订单可以接手,快去抢单吧!',
					'order_no'     => 'ORDER201803101720198213538',
					'accid'        => '',
				],
				'type' => 10002,
			]),
		];
		$result = $yun->sendMsg($Msg);
		dd($result);
		if ($result['code'] != 200) {

			return false;
		}
		dd($result);
	}

	public function testSendNormalMsg()
	{
		$yun    = new Yunxin();
		$data   = [
			'from' => 'ddboss',
			'ope'  => 0,
			// 'to'   => 'go_000000204',
			'to'   => 'fadan001',
			'type' => 0,
			'body' => '{
				"msg":"213"
			}',
		];
		$result = $yun->sendMsg($data);
		dd($result);
		if ($result['code'] != 200) {
			return false;
		}
		dd($result);
	}

	public function testSendBatchMsg()
	{
		$yun    = new Yunxin();
		$data   = [
			'fromAccid' => 'idailian',
			'toAccids'  => json_encode(['fadan001', 'ddboss']),
			'type'      => 100,
			'body'      => '{"msg":"asdas"}',
			// 'body' => '{"msg":"BOND201803021337"}'
			// 'body' => "BOND201803021337"
		];
		$result = $yun->sendBatchMsg($data);
		if ($result['code'] == 200) {
			return false;
		}

		dd($result);
	}

	/**
	 * 获取聊天室信息
	 * array:2 [
	 * "chatroom" => array:10 [
	 * "roomid" => 21425331
	 * "valid" => true
	 * "announcement" => "This is ok"
	 * "queuelevel" => 0
	 * "muted" => false
	 * "name" => "Let us go to car for fly"
	 * "broadcasturl" => "http://www.cqiu.top/mv/2312312"
	 * "onlineusercount" => 0
	 * "ext" => ""
	 * "creator" => "go_000000171"
	 * ]
	 * "code" => 200
	 * ]
	 */
	public function testGetCharRooms()
	{
		$yun = new Yunxin();

		$result = $yun->chatRoomGets(21425331);
		if ($result) {
			dd($result);
		}
	}

	public function testTime()
	{
		dd(date('Y-m-d H:i:s', 1521463364));
		// dd(time(1521467514));
		// dd(strtotime(1521467514));
		// dd(Carbon::now()->subDay(1));
	}

	public function testSessionMsg()
	{
		$yun    = new Yunxin();
		$data   = [
			'from'      => 'liexiang',
			'to'        => 'go_000000176',
			'begintime' => 1520956800000,
			'endtime'   => time() * 1000,
			'limit'     => 50,
		];
		$result = $yun->querySessionMsg($data);
		dd($result);
	}

}