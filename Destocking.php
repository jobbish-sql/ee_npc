<?php

namespace EENPC;

const TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT = 6;

// TODO: finish headers
// TODO: add debug logging and add screen logging using new API
// log_country_message($cnum, $message)
// Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_tu}; Q: ".$q);

/*
NAME: execute_destocking_actions
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function execute_destocking_actions($cnum, $strategy, $reset_end_time, $server_seconds_per_turn, $max_market_package_time_in_seconds, $pm_oil_sell_price, $pm_food_sell_price, &$next_play_time) {
	$reset_seconds_remaining = ($server->reset_end - time());

	$c = get_advisor();	// create object

	// FUTURE: cancel all SOs
	
	// change indy production to 100% jets
	$c->setIndy('pro_j');

	// keep 8 turns for possible recall tech, recall goods, double sale of goods, double sale of tech
	// this is likely too conservative but it doesn't matter if bots lose a few turns of income at the end
	$turns_to_keep = 8;
	$money_to_reserve = max(11 * min(0, $c->income), 55000000);
	temporary_cash_or_tech_at_end_of_set ($c, $strategy, $turns_to_keep, $money_to_reserve);

	// FUTURE: replace 0.81667 (max demo mil tech) with game API call
	// reasonable to assume that a greedy demo country will resell bushels for $2 less than max PM sell price on all servers
	// FUTURE: use 1 dollar less than max on clan servers?
	$estimated_public_market_bushel_sell_price = round($pm_food_sell_price / 0.81667) - 2; // subtract 2 from demo max private market sell price

	// should_dump_bushels_on_private_market ($reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $public_market_tax_rate) {
	// FUTURE: buy mil tech - PM purchases, bushel reselling?, bushel selling - I expect this to be an annoying calculation
	
	// FUTURE: switch governments if that would help
	
	// FUTURE: recall bushels if there are enough of them compared to expenses? no API

	// make sure everything is correct because destocking is important
	$c = get_advisor();
	
	// resell bushels if profitable
	$pm_info = PrivateMarket::getInfo();
	$private_market_bushel_price = $pm_info->sell_price->m_bu;
	$current_public_market_bushel_price = PublicMarket::price('m_bu');	
	$should_attempt_bushel_reselling = can_resell_bushels_from_public_market ($private_market_bushel_price, $c->tax(), $current_public_market_bushel_price, $max_profitable_public_market_bushel_price);
	
	if ($should_attempt_bushel_reselling)
		do_public_market_bushel_resell_loop ($c, $max_profitable_public_market_bushel_price);

	// FUTURE: right now we force a private market sale if money is short, we could be smarter about this
	// no need to worry about food because dump_bushel_stock_handles it
	$force_private_sale = (has_money_for_turns(1, $c->money, $c->taxes, $c->expenses, 0) ? false : true);
	dump_bushel_stock($c, $reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $force_private_sale);

	// FUTURE: consider burning oil to generate private market units

	// $pm_info = PrivateMarket::getInfo(); comment out because it's ok if prices are a little wrong here
	$reserved_money_for_future_private_market_purchases = 0;

	$max_spend = $c->money - max(10 * min(0, $c->income), 50000000); // 50 M seems like a reasonable amount to keep to run turns as needed in the end
	// we don't do an expenses calculation because expenses will grow quite rapidly as we dump our stock

	// spend money on public market and private market, going in order by dpnw
	// FUTURE: replenishment rates should come from game API
	$destock_units_to_replenishment_rate = array("m_tr" => 3.0, "m_ta" => 1.0, "m_j" => 2.5, "m_tu" => 2.5);
	foreach($destock_units_to_replenish_rate as $military_unit => $replenishment_rate) {	
		buyout_up_to_private_market_unit_dpnw ($c, $pm_info->buy_price->$military_unit, $military_unit, $max_spend);
		// set aside money for future private market unit generation		
		$reserved_money_for_future_private_market_purchases += estimate_future_private_market_capacity_for_military_unit($pm_info->buy_price->$military_unit, $c->land, $replenishment_rate, $reset_seconds_remaining, $server_seconds_per_turn);
		$max_spend = $c->money - $reserved_money_for_future_private_market_purchases;
	}
	
	// check if this is our last shot at destocking
	if(is_final_destock_attempt($reset_seconds_remaining, $server_seconds_per_turn)) {
		// FUTURE: recall stuck bushels if there are enough of them compared to expenses? no API
		
		// note: a human would recall all military goods here, but I don't care if bots lose NW at the end if it allows a human to buy something

		final_dump_all_resources($c, $pm_oil_sell_price);
		
		buyout_up_to_public_market_dpnw($c, 5000, $c->money, true); // buy anything ($10000 tech is 5000 dpnw)
	}
	else { // not final attempt
		// FUTURE: use SOs and recent market data to avoid getting ripped off
		$max_dpnw = calculate_maximum_dpnw_for_public_market_purchase ($reset_seconds_remaining, $server_seconds_per_turn, $pm_info->buy_price->m_tu, $c->tax());
		buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, false); // don't buy tech, maybe the humans want it

		// consider putting up military for sale if we have money to play a turn and we expect to have at least 100 M in unspent PM capacity
		$target_sell_amount = $reserved_money_for_future_private_market_purchases - get_total_value_of_on_market_goods($c);
		if(has_money_for_turns(1, $c->money, $c->taxes, $c->expenses, 0, true) and $target_sell_amount > 100000000) {

			// buy food to play a turn if needed
			if(buy_full_food_quantity_if_possible($c, get_food_needs_for_turns(1, $c->foodpro, $c->foodcon, true), 80, 0)) { // have enough food
				$did_resell = resell_military_on_public ($c, $target_sell_amount);
				// TODO: log something here
			}
		}
		
		// FUTURE: check if should dump tech
		// FUTURE: dump tech	
	}
	
	// calculate next play time
	$next_login_time = now() + TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn;
	return $c;
}


/*
NAME: get_earliest_possible_destocking_start_time_for_country
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$strategy
	
*/
function get_earliest_possible_destocking_start_time_for_country($bot_secret_number, $strategy, $reset_start_time, $reset_end_time) {
	// I just made this up, can't say that they are any good - Slagpit 20210316
	// techer is last 75% to 90% of reset
	// rainbow and indy are last 90% to 95% of reset
	// farmer and casher are last 95% to 98.5% of reset	
	// note: TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT value should allow for at least two executions for all strategies

	switch ($strategy) {
		case 'F':
			$window_start_time_factor = 0.95;
			$window_end_time_factor = 0.985;
			break;
		case 'T':
			$window_start_time_factor = 0.75;
			$window_end_time_factor = 0.9;
			break;
		case 'C':
			$window_start_time_factor = 0.95;
			$window_end_time_factor = 0.985;
			break;
		case 'I':
			$window_start_time_factor = 0.90;
			$window_end_time_factor = 0.95;
			break;
		default:
			$window_start_time_factor = 0.90;
			$window_end_time_factor = 0.95;
	}

	$number_of_seconds_in_window = ($window_end_time_factor - $window_start_time_factor) * ($reset_end_time - $reset_start_time);
	$rand_factor = 0.001 * decode_bot_secret($bot_secret_number, 3); // random number three digit number between 0.000 and 0.999 that's fixed for each country

	// example of what we're doing here: suppose that start time factor is 90%, end time is 95% and random number is 0.25
	// the earliest destock time then is after 25% has passed of the interval starting with 90% of the reset and ending with 95% of the reset
	// so for a 100 day reset, this country should start destocking after 92.5 days have passed
	return $reset_start_time + $window_start_time_factor * ($reset_end_time - $window_start_time_factor) + $rand_factor * $number_of_seconds_in_window;
}


/*
NAME: resell_military_on_public
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function resell_military_on_public (&$c, $target_sell_amount, $min_sell_amount = 50000000) {
	// sell in an opposite order from what I expect humans to do: turrets, then jets, then tanks, than troops

	$total_value_in_new_market_package = 0;
	$pm_info = PrivateMarket::getRecent();

	$market_quantities = [];
	$market_prices = [];

	$unit_types = array('m_tu', 'm_j', 'm_ta', 'm_tr');
	foreach($unit_types as $unit_type) {
		$pm_price = $pm_info->buy_price->$unit_type;
		// FUTURE: prices could be smarter, especially for techers with long destock periods
		// we're basically just throwing military up at a somewhat expensive price
		$public_price = Math::half_bell_truncate_left_side(floor(1.2 * $pm_price), 3 * $pm_price, $pm_price);
		$max_sell_amount = can_sell_mil($c, $unit_type); // FUTURE - this function should account for increased express package sizes
		$units_to_sell = min($max_sell_amount, floor(($target_sell_amount - $total_value_in_new_market_package) / ((2 - $c->tax()) * $public_price)));

		$total_value_in_new_market_package += $units_to_sell * $public_price * (2 - $c->tax());

		$market_quantity[] = $units_to_sell;
		$market_prices[] = $public_price;
	}

	if ($total_value_in_new_market_package > $min_sell_amount) { // don't bother with sales under $50 M
		PublicMarket::sell($c, $market_quantity, $market_prices); // FUTURE: do something with the return object
		return true;
	}
	else {
		return false;
	}
}


/*
NAME: temporary_cash_or_tech_at_end_of_set
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function temporary_cash_or_tech_at_end_of_set (&$c, $strategy, $turns_to_keep, $money_to_reserve) {
	// FUTURE: this code is overly simple and shouldn't exist here - it should call standard code used for teching and cashing
	$current_public_market_bushel_price = PublicMarket::price('m_bu');
	while($c->turns > $turns_to_keep) {
		// expenses might be high so go turn by turn?

		$incoming_money_per_turn = ($strategy == 'T' ? 1.0 : 1.2) * $c->taxes;
		// check cash
		if(!has_money_for_turns(1, $c->money, $incoming_money_per_turn, $c->expenses, $money_to_reserve)) {
			// not enough money to play a turn - can we make up the difference by selling a turn's worth of food production?
			if($c->foodnet > 0)
				sell_single_good($c, 'm_bu', min($c->food, $c->foodnet));
			
			if (!has_money_for_turns(1, $c->money, $incoming_money_per_turn, $c->expenses, $money_to_reserve)) // playing turns is no longer productive
				break;
		}

		// try to buy food if needed, quit if we can't find cheap enough food
		if(!buy_full_food_quantity_if_possible($c, get_food_needs_for_turns(1, $c->foodpro, $c->foodcon), 50, $money_to_reserve))
			break;

		// we should have enough food and money to play a turn
		if ($strategy == 'T') {
			tech(['mil' => $c->tpt]);	// FUTURE: adjust for earthquakes
		}
		else {
			cash($c, 1); // TODO: does $c->income get refreshed after cashing? concerned about expenses rising for CIs for example
		}
	}
}


/*
NAME: buyout_up_to_private_market_unit_dpnw
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function buyout_up_to_private_market_unit_dpnw(&$c, $pm_buy_price, $unit_type, $max_spend) {
	// FUTURE: error checking on $unit_type
	
	$unit_to_nw_map = array("m_tr" => 0.5, "m_j" => 0.6, "m_tu" => 0.6, "m_ta" => 2.0); // FUTURE: this is stupid
	$pm_unit_nw = $unit_to_nw_map($unit_type);
	$max_dpnw = $pm_buy_price / $pm_unit_nw;

	$c = get_advisor();	// money must be correct because we get an error if we try to buy too much

	buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend, true);
		
	$c = get_advisor(); // money must be correct because we get an error if we try to buy too much
	
	// fresh update because maybe prices changed or units decayed
	$new_pm_info = PrivateMarket::getInfo();
	$pm_purchase_amount = min($pm_info->available->$unit_type, floor(min($c->money, $max_spend) / $pm_info->buy_price->$unit_type));
	if ($pm_purchase_amount > 0)
		PrivateMarket::buy($c, [$unit_type => $pm_purchase_amount]);
	return;	
}


/*
NAME: buyout_up_to_public_market_dpnw
PURPOSE: 
RETURNS: total spent
PARAMETERS:
	$
	$
	
*/
function buyout_up_to_public_market_dpnw(&$c, $max_dpnw, $max_spend, $military_units_only, $total_spent = 0, $recursion_level = 0) {	
	// do some setup to limit API calls for public market info
	$unit_to_nw_map = array("m_tr" => 0.5, "m_j" => 0.6, "m_tu" => 0.6, "m_ta" => 2.0); // FUTURE: this is stupid
	if(!$military_units_only) {	// FUTURE: this is also stupid
		$unit_to_nw_map["mil"] = 2.0;
		$unit_to_nw_map["med"] = 2.0;
		$unit_to_nw_map["bus"] = 2.0;
		$unit_to_nw_map["res"] = 2.0;
		$unit_to_nw_map["agri"] = 2.0;
		$unit_to_nw_map["war"] = 2.0;
		$unit_to_nw_map["ms"] = 2.0;
		$unit_to_nw_map["weap"] = 2.0;
		$unit_to_nw_map["indy"] = 2.0;
		$unit_to_nw_map["spy"] = 2.0;
		$unit_to_nw_map["sdi"] = 2.0;
	}
	
	$candidate_purchase_prices_by_unit = [];
	$candidate_dpnw_by_unit = [];
	foreach($unit_to_nw_map as $unit_name => $nw_for_unit) {
		$public_market_price = $public_market_tax_rate * PublicMarket::price($unit_name);
		if ($public_market_price != null) {
			$candidate_purchase_prices_by_unit[$unit_name] = $public_market_price;
			$candidate_dpnw_by_unit[$unit_name] = $public_market_price / $unit_to_nw_map[$unit_name];
		}
	}
	
	$missed_purchases = 0;
	$total_purchase_loops = 0;
	// spend as much money as possible on public market at or below $max_dpnw
	while ($total_spent + 10000 < $max_spend and $missed_purchases < 100 and $total_purchase_loops < 500) {
		if (empty($candidate_dpnw_by_unit)) // out of stuff to buy?
			break;
			
		sort($candidate_dpnw_by_unit); // get next good to buy, undefined order in case of ties is fine
		$best_unit = array_key_first($candidate_dpnw_by_unit);
		$best_unit_quantity = floor($max_spend / $candidate_purchase_prices_by_unit[$best_unit]); // TODO: I suspect ignoring market quantity like this is fine, but test it
		$best_unit_price = $candidate_purchase_prices_by_unit[$best_unit];
		
		$money_before_purchase = $c->money;
		PublicMarket::buy($c, [$best_unit => $best_unit_quantity], [$best_unit => $best_unit_price]);
		$diff = $c->money - $money_before_purchase; // I don't like this but the return structure of PublicMarket::buy is tough to deal with
		$total_spent += $diff;
		if ($diff == 0) {
			$missed_purchases++;
			if ($missed_purchases % 5 == 0) // maybe cash was stolen or an SO was filled, so do an expensive refresh
				$c = get_advisor();	
		}

		// refresh price
		$new_public_market_price = $public_market_tax_rate * PublicMarket::price($best_unit);
		if ($new_public_market_price == null)
			unset($candidate_dpnw_by_unit[$best_unit]);
		else {			
			$candidate_purchase_prices_by_unit[$unit_name] = $new_public_market_price;
			$candidate_dpnw_by_unit[$unit_name] = $new_public_market_price / $unit_to_nw_map[$unit_name];			
		}

		$total_purchase_loops++;
	}

	// some units might have shown up after we last refreshed prices, so call up to 2 more times recursively
	if ($recursion_level < 2)
		buyout_up_to_public_market_dpnw($c, $max_dpnw, $max_spend - $total_spent, $military_units_only, $total_spent, $recursion_level + 1);

	return $total_spent;
}


/*
NAME: estimate_future_private_market_capacity_for_military_unit
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function estimate_future_private_market_capacity_for_military_unit($m_price, $land, $replenishment_rate, $reset_seconds_remaining, $server_seconds_per_turn) {
	return ($m_price * $land * $replenishment_rate * floor($reset_seconds_remaining / $server_seconds_per_turn));
}

/*
NAME: can_resell_bushels_from_public_market
PURPOSE: checks if current public market bushel price allows for profitable reselling on private market
RETURNS: true if reselling is profitable, false otherwise
PARAMETERS:
	$private_market_bushel_price - private market bushel price in dollars
	$public_market_tax_rate - public market tax rate as a decimal: 6% would be 1.06 for example
	$current_public_market_bushel_price - current public market bushel price in dollars
	$max_profitable_public_market_bushel_price - output parameter that has the maximum public price that is still profitable
*/
function can_resell_bushels_from_public_market ($private_market_bushel_price, $public_market_tax_rate, $current_public_market_bushel_price, &$max_profitable_public_market_bushel_price) {	
	$max_profitable_public_market_bushel_price = -1 + ceil($private_market_bushel_price / $public_market_tax_rate);	
	return ($max_profitable_public_market_bushel_price < $current_public_market_bushel_price ? true : false);
}


/*
NAME: do_public_market_bushel_resell_loop 
PURPOSE: in a loop, buys bushels off public to sell on private market
RETURNS: nothing
PARAMETERS:
	$c - country object
	$max_public_market_bushel_purchase_price - maximum public price that is still profitable
*/
function do_public_market_bushel_resell_loop (&$c, $max_public_market_bushel_purchase_price) {	
	$current_public_market_bushel_price = PublicMarket::price('m_bu');
	$price_refreshes = 0;
	
	// limited to 500 because I don't want an insane number of purchases if the buyer has low cash compared to market volume
	for ($number_of_purchases = 0; $number_of_purchases < 500; $number_of_purchases++) {
		if ($current_public_market_bushel_price > $max_public_market_bushel_purchase_price)
			break;
		
		$max_quantity_to_buy_at_once = floor($c->money / ($current_public_market_bushel_price * $c->tax()));

		$previous_food = $c->food;
		$result = PublicMarket::buy($c, ['m_bu' => $max_quantity_to_buy_at_once], ['m_bu' => $current_public_market_bushel_price]);
		$bushels_purchased_quantity = $c->food - $previous_food;
		if ($bushels_purchased_quantity == 0) {
			$current_public_market_bushel_price = PublicMarket::price('m_bu'); // most likely explanation is price changed, so update it
			$price_refreshes++;
			if ($price_refreshes % 5 == 0) // maybe cash was stolen or an SO was filled, so do an expensive refresh
				$c = get_advisor();
		}			
		else // sell what we purchased on the private market
			PrivateMarket::sell_single_good($c, 'm_bu', $bushels_purchased_quantity);
	}
	
	return;
}


/*
NAME: calculate_maximum_dpnw_for_public_market_purchase
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function calculate_maximum_dpnw_for_public_market_purchase ($reset_seconds_remaining, $server_seconds_per_turn, $private_market_turret_price, $public_market_tax_rate) {
	$min_dpnw = ($private_market_turret_price / 0.6) / $public_market_tax_rate; // always buy better than private turret price	
	$max_dpnw = max($min_dpnw + 10, 500 - 2 * floor($reset_seconds_remaining / $server_seconds_per_turn)); // FUTURE: this is stupid and players who read code can take advantage of it
	$std_dev = ($max_dpnw - $min_dpnw) / 2;

	return Math::half_bell_truncate_left_side($min_dpnw, $max_dpnw, $std_dev);
}


/*
NAME: is_final_destock_attempt
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function is_final_destock_attempt ($reset_seconds_remaining, $server_seconds_per_turn) {
	return ($reset_seconds_remaining / $server_seconds_per_turn < TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT ? true : false);
}


/*
NAME: dump_bushel_stock
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function dump_bushel_stock(&$c, $reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $force_private_sale = false) {
	// FUTURE: recall bushels if profitable (API doesn't exist)

	$bushels_to_sell = $c->food - get_food_needs_for_turns(5, $c->foodpro, $c->foodcon, true);

	if ($bushels_to_sell <= 0)
		return;
	
	if($force_private_sale or should_dump_bushels_on_private_market($reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $c->tax())) {			
		PrivateMarket::sell_single_good($c, 'm_bu', $bushels_to_sell);
	}
	else { // sell on public
		PublicMarket::sell($c, ['m_bu' => $bushels_to_sell], ['m_bu' => $estimated_public_market_bushel_sell_price]); 
	}
	return;
}


/*
NAME: should_dump_bushels_on_private_market
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function should_dump_bushels_on_private_market ($reset_seconds_remaining, $server_seconds_per_turn, $max_market_package_time_in_seconds, $private_market_bushel_price, $estimated_public_market_bushel_sell_price, $public_market_tax_rate) {
	// sell on private if not enough time to sell on public
	if ($reset_seconds_remaining < $max_market_package_time_in_seconds + TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT * $server_seconds_per_turn)
		return true;

	// sell on private if the public price isn't at least $1.5 better
	if ($estimated_public_market_bushel_sell_price * (2 - $public_market_tax_rate) < 1.5 + $private_market_bushel_price)
		return true;
	
	return false;
}


/*
NAME: final_dump_all_resources
PURPOSE: 
RETURNS: 
PARAMETERS:
	$
	$
	
*/
function final_dump_all_resources(&$c, $pm_oil_sell_price) {
	// no more turns to play for the reset so sell food for any price on private market
	PrivateMarket::sell_single_good($c, 'm_bu', $c->food);

	if($pm_oil_sell_price > 0) // sell oil if the server allows it
		PrivateMarket::sell_single_good($c, 'm_oil', $c->oil);

	return;
}