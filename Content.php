<?php
/**
 * @name Content
 * @author 1.0.0
 * @main Content\Content
 * @version 1.0.0
 * @api 3.9.5
 */
namespace Content;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\utils\Config;

use pocketmine\Player;

use pocketmine\item\Item;

use pocketmine\level\Level;

use pocketmine\math\Vector3;

use pocketmine\nbt\tag\IntTag;

use pocketmine\level\particle\FloatingTextParticle;

use pocketmine\command\PluginCommand;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\network\mcpe\protocol\ModalFormRequestPacket; // 커스텀 UI 관련
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket; // 커스텀 UI 관련
use pocketmine\event\server\DataPacketReceiveEvent;

class Content extends PluginBase implements Listener {

  public function onEnable(){
    $this->getServer()->getPluginManager()->registerEvents($this, $this);

    $this->data =new Config($this->getDataFolder(). 'ContentList.yml', Config::YAML,[
      "콘텐츠목록" => []
    ]);
    $this->db = $this->data->getAll();

    $this->subdata =new Config($this->getDataFolder(). 'SubList.yml', Config::YAML);
    $this->sub = $this->subdata->getAll();

    $cmd = new PluginCommand('콘텐츠', $this);
    $cmd->setDescription('콘텐츠 명령어');

    $this->getServer()->getCommandMap()->register('콘텐츠', $cmd);

  }

  public function save(){
    $this->data->setAll($this->db);
    $this->data->save();

    $this->subdata->setAll($this->sub);
    $this->subdata->save();
  }

  public function ContentUI(Player $player){
    $button = [
      'type' => 'form',
      'title' => '콘텐츠UI',
      "content" => '원하시는 활동을 눌러주세요.',
      'buttons' => [
        [
          'text' => '콘텐츠 생성'
        ],
        [
          'text' => '콘텐츠 목록'
        ],
        [
          'text' => '콘텐츠 삭제'
        ],
      ]
    ];
    return json_encode($button);
  }

  public function ContentCreateUI(Player $player){
    $button = [
      'type' => 'custom_form',
      'title' => '콘텐츠UI',
      "content" => [
        [
          'text' => '1. 켄텐츠의 이름을 적어주세요',
          "type" => "input"
        ],
        [
          'text' => '2. 생성할 보상을 설정해주세요',
          'type' => 'dropdown',
          'options' => ['아이템보상', '돈보상'] //번호0 : 아이템보상   번호1 : 돈보상
        ],
        [
          'text' => '3. 설정한 보상이 돈이면 금액을 아이템보상이면 갯수를 써주세요',
          "type" => "input"
        ],
      ]
    ];
    return json_encode($button);
  }

  public function ContentListUI(Player $player){
    $count = count($this->db['콘텐츠목록']);
    for($i =1; $i <= $count; $i++){
      $bt[] = array('text' => $i."번 : ".$this->db['콘텐츠목록'][$i]);
    }
    $button = [
      'type' => 'form',
      'title' => '콘텐츠UI',
      "content" => '< 콘텐츠 목록 >',
      "buttons" => $bt
    ];
    return json_encode($button);
}

  public function MainUI (DataPacketReceiveEvent $event) {//콘텐츠UI이벤트
    $p = $event->getPacket ();
		$player = $event->getPlayer ();
		if ($p instanceof ModalFormResponsePacket and $p->formId == 1122332 ) {
			$name = json_decode ( $p->formData, true );
      if($name === 0){
        $pk = new ModalFormRequestPacket ();
        $pk->formId = 1122333;
        $pk->formData = $this->ContentCreateUI($player);
        $player->dataPacket ($pk);
      }
      if($name === 1 ){
        if(count($this->db['콘텐츠목록']) === 0 ){
          $player->sendMessage('만들어진 콘텐츠가 없습니다.');
          return true;
        }
          $pk = new ModalFormRequestPacket ();
          $pk->formId = 1122334;
        $pk->formData = $this->ContentListUI($player);
        $player->dataPacket ($pk);
      }

      if($name === 2){
        $this->db['콘텐츠삭제'][strtolower($player->getName())] = '삭제';
        $this->save();

        $player->sendMessage('제거할 콘텐츠 블럭을 터치해주세요.');
      }
    }
  }

  public function onUIevnet (DataPacketReceiveEvent $event) {//콘텐츠UI이벤트
    $packet = $event->getPacket ();
    $player = $event->getPlayer();
     if ($packet instanceof ModalFormResponsePacket) {
       if($packet->formId === 1122333){
         $name = json_decode ( $packet->formData, true );
			if ( $name[0] == null or $name[2] == null) {
				$player->sendMessage ("1번과 3번을 써주세요.");
			} else {
        if($name[1] === 0){
          if(isset($this->sub['콘텐츠'][$name[0]])) {
            $player->sendMessage('이미 있으신 이름의 콘텐츠 입니다.');
            return true;
          }
          $item = $player->getInventory()->getItemInHand();

          $this->db['콘텐츠생성'][strtolower($player->getName())]['아이템'] = [
            '아이템' => '아이템',
            '이름' => $name[0],
            '갯수' => $name[2],
            '아이템' => $item->jsonSerialize()
          ];
          $player->sendMessage('생성하고싶은 블럭을 터치해주세요.');
          $this->save();
        }

        if($name[1] === 1){
          if(isset($this->sub['콘텐츠'][$name[0]])){
            $player->sendMessage('이미 있으신 이름의 콘텐츠 입니다.');
            return true;
          }
          $this->db['콘텐츠생성'][strtolower($player->getName())]['돈'] = [
            '돈' => '돈',
            '이름' => $name[0],
            '금액' => $name[2]
          ];
          $player->sendMessage('생성하고싶은 블럭을 터치해주세요.');
          $this->save();
        }
			}
    }
	}
}

  public function onTouch(PlayerInteractEvent $event){
    $player = $event->getPlayer();
    $name = strtolower($player->getName());
    $pos = $event->getBlock()->getX().":". $event->getBlock()->getY().":". $event->getBlock()->getZ().":".$event->getBlock()->getLevel()->getFolderName();

    if(isset($this->db['콘텐츠삭제'][$name])) {
      if(isset($this->db['콘텐츠'][$pos])) {
        if(isset($this->db['콘텐츠'][$pos]['아이템'])) {
          $player->sendMessage('성공적으로 '.$this->db['콘텐츠'][$pos]['아이템']['이름'].'을 제거 하셨습니다.');
          unset($this->db['콘텐츠목록'][$this->sub['서브'][$this->db['콘텐츠'][$pos]['아이템']['이름']]]);
          unset($this->sub['서브'][$this->db['콘텐츠'][$pos]['아이템']['이름']]);
          unset($this->db['콘텐츠'][$pos]);
          unset($this->sub['콘텐츠'][$pos]);
          unset($this->db['콘텐츠삭제'][$name]);
          $this->save();
          return true;
      }
        if(isset($this->db['콘텐츠'][$pos]['돈'])) {
        $player->sendMessage('성공적으로 '.$this->db['콘텐츠'][$pos]['돈']['이름'].'을 제거 하셨습니다.');
        unset($this->db['콘텐츠목록'][$this->sub['서브'][$this->db['콘텐츠'][$pos]['돈']['이름']]]);
        unset($this->sub['서브'][$this->db['콘텐츠'][$pos]['돈']['이름']]);
        unset($this->db['콘텐츠'][$pos]);
        unset($this->sub['콘텐츠'][$pos]);
        unset($this->db['콘텐츠삭제'][$name]);
        $this->save();
        return true;
      }
    }else{
      $player->sendMessage('당신 터치한 블럭은 콘텐츠 블럭이 아닙니다.');
    }
  }

    if(isset($this->db['콘텐츠'][$pos])){
      if(isset($this->db['콘텐츠'][$pos]['아이템'])){
        if(isset($this->db['클리어'][$name][$this->db['콘텐츠'][$pos]['아이템']['이름']]) && $this->db['클리어'][$name][$this->db['콘텐츠'][$pos]['아이템']['이름']] == date ("d")) {
          $player->addTitle('§6§l[ §f! §6]');
          $player->addSubTitle('§f이미 §6'.$this->db['콘텐츠'][$pos]['아이템']['이름'].'§f를 클리어 했습니다.');
          return true;
        }
        $item = Item::jsonDeserialize($this->db['콘텐츠'][$pos]['아이템']['아이템']);
        $item->setCount($this->db['콘텐츠'][$pos]['아이템']['갯수']);
        $player->getInventory()->addItem($item);

        $player->addTitle('§6§l[ §f! §6]');
        $player->addSubTitle('§6'.$this->db['콘텐츠'][$pos]['아이템']['이름'].'§f를 클리어 했습니다.');

        $this->getServer()->broadcastMessage('§f§l────────────');
        $this->getServer()->broadcastMessage('§6▶ §f'.$name.'님께서 '.$this->db['콘텐츠'][$pos]['아이템']['이름'].'을 클리어 하셨습니다.');
        $this->getServer()->broadcastMessage('§f§l────────────');
        $this->db['클리어'][$name][$this->db['콘텐츠'][$pos]['아이템']['이름']] = date ("d");
        $this->save();
      }
      if(isset($this->db['콘텐츠'][$pos]['돈'])){
        if(isset($this->db['클리어'][$name][$this->db['콘텐츠'][$pos]['돈']['이름']]) && $this->db['클리어'][$name][$this->db['콘텐츠'][$pos]['돈']['이름']] == date ("d")){
          $player->addTitle('§6§l[ §f! §6]');
          $player->addSubTitle('§f이미 §6'.$this->db['콘텐츠'][$pos]['돈']['이름'].'§f를 클리어 했습니다.');
          return true;
        }
        $item = Item::get(Item::PAPER,0,1);
        $item->setNamedTagEntry(new IntTag("check", $this->db['콘텐츠'][$pos]['돈']['돈']));
        $item->setCustomName('돈 '.$this->db['콘텐츠'][$pos]['돈']['돈'].'원');

        $player->getInventory()->addItem($item);

        $player->addTitle('§6§l[ §f! §6]');
        $player->addSubTitle('§6'.$this->db['콘텐츠'][$pos]['돈']['이름'].'§f를 클리어 했습니다.');

        $this->getServer()->broadcastMessage('§f§l────────────');
        $this->getServer()->broadcastMessage('§6▶ §f'.$name.'님께서 '.$this->db['콘텐츠'][$pos]['돈']['이름'].'을 클리어 하셨습니다.');
        $this->getServer()->broadcastMessage('§f§l────────────');
        $this->db['클리어'][$name][$this->db['콘텐츠'][$pos]['돈']['이름']] = date ("d");
        $this->save();
    }
  }

    if(isset($this->db['콘텐츠생성'][$name])){

      if(isset($this->db['콘텐츠생성'][$name]['돈'])){
        $this->db['콘텐츠'][$pos]['돈'] = [
          '이름' => $this->db['콘텐츠생성'][$name]['돈']['이름'],
          '돈' => $this->db['콘텐츠생성'][$name]['돈']['금액']
        ];

        $this->sub['콘텐츠'][$this->db['콘텐츠생성'][$name]['돈']['이름']] = '콘텐츠';
        $this->sub['서브'][$this->db['콘텐츠생성'][$name]['돈']['이름']] = count($this->db['콘텐츠']);
        $this->db['콘텐츠목록'][count($this->db['콘텐츠'])] = $this->db['콘텐츠생성'][$name]['돈']['이름'];
        $player->sendMessage('성공적으로 콘텐츠를 생성하셨습니다.');
        $this->getServer()->broadcastMessage($name.'님께서 '.$this->db['콘텐츠생성'][$name]['돈']['이름'].'콘텐츠를 생성했습니다.');
        unset($this->db['콘텐츠생성'][$name]);
        $this->save();
      }

      if(isset($this->db['콘텐츠생성'][$name]['아이템'])){
      $this->db['콘텐츠'][$pos]['아이템'] = [
        '이름' => $this->db['콘텐츠생성'][$name]['아이템']['이름'],
        '아이템' => $this->db['콘텐츠생성'][$name]['아이템']['아이템'],
        '갯수' =>  $this->db['콘텐츠생성'][$name]['아이템']['갯수']
      ];
      $this->sub['콘텐츠'][$this->db['콘텐츠생성'][$name]['아이템']['이름']] = '콘텐츠';
      $this->sub['서브'][$this->db['콘텐츠생성'][$name]['아이템']['이름']] = count($this->db['콘텐츠']);
      $this->db['콘텐츠목록'][count($this->db['콘텐츠'])] = $this->db['콘텐츠생성'][$name]['아이템']['이름'];
      $player->sendMessage('성공적으로 콘텐츠를 생성하셨습니다.');
      $this->getServer()->broadcastMessage($name.'님께서 '.$this->db['콘텐츠생성'][$name]['아이템']['이름'].'콘텐츠를 생성했습니다.');
      unset($this->db['콘텐츠생성'][$name]);
      $this->save();
    }
  }
}



  public function onCommand(CommandSender $sender, Command $command, $lable , array $args) :bool {
    $cmd = $command->getName();
    $name = strtolower($sender->getName());
    if($cmd == '콘텐츠'){
      if(isset($args[0])){
        $sender->sendMessage('/콘텐츠 │ 콘텐츠 UI창을 띄웁니다.');
        return true;
      }
      $p = new ModalFormRequestPacket ();
      $p->formId = 1122332;
      $p->formData = $this->ContentUI($sender);
      $sender->dataPacket ($p);
      return true;
    }
  }
}
