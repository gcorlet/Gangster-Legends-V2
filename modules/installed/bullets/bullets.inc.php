<?php

    class bullets extends module {
        
        public $pageName = 'Bullet Factory';
        public $allowedMethods = array('bullets'=>array('type'=>'post'));
        
        public function constructModule() {
            
            $location = $this->db->prepare("SELECT * FROM locations WHERE L_id = ?");
            $location->execute(array($this->user->info->US_location));
            $loc = $location->fetchObject();
            
            $this->bulletCost = $loc->L_bulletCost;
            
            $this->property = new Property($this->user, "bullets");
            $owner = $this->property->getOwnership();

            if (!!$owner["cost"]) {
                $this->bulletCost = $owner["cost"];
            }

            $owner["locationName"] = $loc->L_name;
            $owner["stock"] = number_format($loc->L_bullets);
            $owner["maxBuy"] = ($this->user->info->US_rank * 25);
            $owner["cost"] = $this->money($this->bulletCost);

            if (!$this->user->checkTimer('bullets')) {
                
                $timeLeft = $this->user->getTimer('bullets');
                $timeLeft = $this->timeLeft($timeLeft);

                $error = array(
                    "text"=>'You have to wait to but more bullets!',
                    "time" => $this->user->getTimer("bullets")
                );
                $this->html .= $this->page->buildElement('timer', $error);
                
            }

            $this->html .= $this->page->buildElement('bulletPage', $owner);
            
        }
        
        public function method_own() {
            $this->property = new Property($this->user, "bullets");
            $owner = $this->property->getOwnership();

            if ($owner["user"]) {

                $user = $this->page->buildElement("userName", $owner);

                return $this->html .= $this->page->buildElement("error", array(
                    "text" => "This property is owned by " . $user
                ));
            }

            if ($this->user->info->US_money < 1000000) {
                return $this->html .= $this->page->buildElement("error", array(
                    "text" => "You need $1,000,000 to buy of this property."
                ));
            }

            $update = $this->db->prepare("
                UPDATE userStats SET US_money = US_money - 1000000 WHERE US_id = :id
            ");
            $update->bindParam(":id", $this->user->id);
            $update->execute();

            $this->property->transfer($this->user->id);

            return $this->html .= $this->page->buildElement("success", array(
                "text" => "You paid $1,000,000 to buy this property."
            ));

        }
        
        public function method_buy() {
            
            $qty = abs(intval($this->methodData->bullets));
            
            $location = $this->db->prepare("SELECT * FROM locations WHERE L_id = ?");
            $location->execute(array($this->user->info->US_location));
            $loc = $location->fetchObject();

            $this->bulletCost = $loc->L_bulletCost;
            
            $this->property = new Property($this->user, "bullets");
            $owner = $this->property->getOwnership();

            if (!!$owner["cost"]) {
                $this->bulletCost = $owner["cost"];
            }
			
            $cost = ($qty * $this->bulletCost);
            $maxBuy = $this->user->info->US_rank * 25;
            
            if ($qty == 0) {
            
                $this->alerts[] = $this->page->buildElement('error', array(
                    "text"=>'Please enter a value greater then 0!'
                ));
            
            } else if (!$this->user->checkTimer('bullets')) {
                
                $timeLeft = $this->user->getTimer('bullets');
                $timeLeft = $this->timeLeft($timeLeft);

                $error = array(
                    "text"=>'You cany buy bullets yet!'
                );
                $this->alerts[] = $this->page->buildElement('error', $error);
                
            } else if ($qty > $maxBuy) {
                $this->alerts[] = $this->page->buildElement('error', array(
                    "text"=>'You can only buy '.number_format($maxBuy).' bullets at once'
                ));
            } else if ($loc->L_bullets < $qty) {
                $this->alerts[] = $this->page->buildElement('error', array(
                    "text"=>'The bullet factory does not have enough stock to fufil this order!'
                ));
            } else if ($cost > $this->user->info->US_money) {
                $this->alerts[] = $this->page->buildElement('error', array(
                    "text"=>'You need ' . $this->money($cost) . " to buy " . $qty . " bullets"
                ));
            } else {
            
                $this->alerts[] = $this->page->buildElement('success', array(
                    "text"=>'You bought '.$qty.' bullets for $'.number_format($cost)
                ));
                
                $query = "
                    UPDATE userStats SET 
                        US_bullets = US_bullets + $qty, 
                        US_money = US_money - :money
                    WHERE
                        US_id = :id
                "; 
                
				
                $uUser = $this->db->prepare($query);
                $uUser->bindParam(":money", $cost);
                $uUser->bindParam(":id", $this->user->id);
                $uUser->execute();
				
				$this->user->updateTimer('bullets', 60, true);
				
                $uLoc = $this->db->prepare("UPDATE locations SET L_bullets = L_bullets - $qty WHERE L_id= :loc");
                $uLoc->bindParam(":loc", $loc->L_id);
				$uLoc->execute();
            }
            
        }
        
    }

?>