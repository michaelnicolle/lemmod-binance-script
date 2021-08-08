<?php

error_reporting(E_ERROR);
/*****

Use this script at your own risk!

Simple calculator for futures exposure calculator (Only USD-M support , no COIN-M)

(c) 2021 - MileCrypto (Lemmod)

*/


/** Setup your Binance API Key and Secret */
/** For security purposes it's advised to limit the API access to your Server IP address */

$user_api_key = 'your_api_key'; // Change this to your Binance API secret (should have access to futures)
$user_api_secret = 'your_api_secret'; // Change this to your Binacne API secret

// Binance connector to make connection to Binance. Plain and simple and only used for connection to futures API
class BinanceConnector
{

    protected $api_key = '';
    protected $api_secret = '';
    protected $base_url = 'https://fapi.binance.com/fapi/'; // Endpoint for binance futures , differs from SPOT API URL

    public function __construct($api_key , $api_secret) {

        if(empty($api_key)) {
            throw new \Exception("API Key not set");
        }

        if(empty($api_secret)) {
            throw new \Exception("API Secret not set");
        }

        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }

    protected function request_info($url , $params = []) {

        $timestamp = (microtime(true) * 1000);
        $params['timestamp'] = number_format($timestamp, 0, '.', '');

        $query = http_build_query($params, '', '&');
        $signature = hash_hmac('sha256', $query, $this->api_secret);

        $endpoint = $this->base_url . $url . '?' . $query . '&signature=' . $signature;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'X-MBX-APIKEY: ' . $this->api_key,
        ));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);

        $output = curl_exec($curl);

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $output = substr($output, $header_size);
        
        curl_close($curl);
        
        $json = json_decode($output, true);

        return $json;

    }

    /**
     * Get the account info , contains all the info we need to get the required information
     */
    public function account_info() {
        return $this->request_info("v2/account");
    }

    /**
     * Get the account info , contains all the info we need to get the required information
     */
    public function income($params) {
        return $this->request_info('v1/income' , $params );
    }


}

// Set your exchange API Key and Secret


$binance = new BinanceConnector($user_api_key , $user_api_secret);
$account_info= $binance->account_info();

$income = $binance->income(['incomeType' => 'REALIZED_PNL' , 'limit' => 1000]);

array_multisort(array_column($income, 'time'),  SORT_DESC , $income);

$total_wallet = $account_info['totalWalletBalance'];
$total_unrealized = $account_info['totalUnrealizedProfit'];
$total_margin_balance = $account_info['totalMarginBalance'];
$total_maintainance_margin = $account_info['totalMaintMargin'];
$total_margin_balance = $account_info['totalMarginBalance'];

$invested = 0;
$current_worth = 0;
$open_positions = array();
$i = 0;
foreach($account_info['positions'] as $position) {
    $invested += $position['positionAmt'] * $position['entryPrice'];
    $current_worth += $position['notional'];

    if ($position['entryPrice'] > 0) {
        $open_positions[$i] = array( 
            'symbol' => $position['symbol'] , 
            'totalAsset' => $position['positionAmt'] ,
            'entryPrice' => $position['entryPrice'] , 
            'currentPrice' => $position['notional'] / $position['positionAmt'] , 
            'profitPercentage' =>  ($position['notional'] ) / ($position['entryPrice'] * $position['positionAmt'] ) ,
            'profitPercentage_min' =>  ( ($position['notional'] ) / ($position['entryPrice'] * $position['positionAmt'] ) - 1) * 100 ,
            'investedWorth' => ($position['entryPrice'] * $position['positionAmt'] ) ,
            'currentWorth' => $position['notional'] ,
            'pnl' => $position['notional'] - ($position['entryPrice'] * $position['positionAmt'] ) 
        );  
        
        $i++;
    }
    
}

array_multisort(array_column($open_positions, 'profitPercentage'),  SORT_DESC , $open_positions);


$exposure = number_format(  ($invested+$total_maintainance_margin) / $total_wallet , 2);
$margin_ratio =  number_format ( ($total_maintainance_margin / ($total_wallet + $total_unrealized)) * 100 , 2).'%';


?>

<!doctype html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        

        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4-4.1.1/jq-3.3.1/dt-1.10.23/r-2.2.7/datatables.min.css"/>
 
		<link rel="stylesheet" type="text/css" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
        <style type="text/css">
        * {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "segoe ui", roboto, oxygen, ubuntu, cantarell, "fira sans", "droid sans", "helvetica neue", Arial, sans-serif;
            font-size: 12px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        body {
            background-color: #FFF;
            margin : 10px;
            padding: 0;
        }

        h1 {
            color : #333;
            border-bottom : 1px dashed #333;
            border-top : 1px dashed #333;
            padding-bottom: 10px;
            margin-bottom: 10px;
            width: 98%;
        }



        </style>

		<script type="text/javascript" src="https://cdn.datatables.net/v/bs4-4.1.1/jq-3.3.1/dt-1.10.23/r-2.2.7/datatables.min.js"></script>
		<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>	


		<title> Binance Exposure calculator </title>

        <script>

        function calculate() {
            
            // Calculate new prices
            var current_asset = document.getElementById('current_asset').value;
            var current_price = document.getElementById('current_price').value;
            var add_asset = document.getElementById('add_asset').value;
            var add_price = document.getElementById('add_price').value;

            var current_worth =  (Number(current_asset) * Number(current_price));
            var added_worth =  (Number(add_asset) * Number(add_price));

            var total_new_worth = current_worth + added_worth;
            var total_new_assets = (Number(current_asset) + Number(add_asset));

            var new_avg_price = total_new_worth / total_new_assets;
            
            result.value = new_avg_price.toFixed(6);

            // Calculate new exposure

            var current_invested = document.getElementById('invested').value;
            var total_wallet = document.getElementById('total_wallet').value;

            document.getElementById('profit_asset').value = total_new_assets;
            document.getElementById('profit_entry').value = new_avg_price.toFixed(6);

            

            var new_exposure = ( Number(current_invested) + Number(added_worth) ) / Number (total_wallet);

            $( "#exposure_dca_result" ).empty().append( "Your new exposure will be approx. : <strong>" + new_exposure.toFixed(2) +"</strong>" ).show();
            
        }

        function calculate_profit() {
            
            // Calculate new prices
            var profit_asset = document.getElementById('profit_asset').value;
            var profit_entry = document.getElementById('profit_entry').value;
            var profit_price = document.getElementById('profit_price').value;

            var profit = ( Number(profit_price) - Number(profit_entry)) * Number(profit_asset);

            $( "#profit_result" ).empty().append( "Your profit will be aprrox. : <strong>$ " + profit.toFixed(2) +"</strong>" ).show();
            
        }

		$(document).ready(function(){

            var trades = {
				responsive: true ,
                columnDefs: [
                    { responsivePriority: 1, targets: -2 },
                    { responsivePriority: 2, targets: -1 },
                    { responsivePriority: 3, targets: 1 },
                    { responsivePriority: 4, targets: 4 },
                ] ,
				"searching": false,
				"paging":   false,
				"ordering": true,
				"info":     false,
                order: [ 
					( [ 7, 'desc' ] ) 
				] ,
			};

            var dca_calc = {
				responsive: true ,
                
				"searching": false,
				"paging":   false,
				"ordering": false,
				"info":     false,
			};

            var realized_pnl = {
				responsive: true ,
               	"searching": true,
				"paging":   true,
				"ordering": true,
				"info":     false,
                order: [ 
					( [ 0, 'desc' ] ) 
				] ,
                
			};
            
			var table_trades = $('#trades').DataTable(trades);    
            var table_dca_calc = $('#dca_calc').DataTable(dca_calc);      
            var table_realized_pnl = $('#realized_pnl').DataTable(realized_pnl);  

            table_trades.on( 'order.dt search.dt', function () {
                table_trades.column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
                    cell.innerHTML = i+1;
                } );
            } ).draw();

            table_trades.on('click', 'tr', function () {
                var data = table_trades.row( this ).data();

                document.getElementById('current_asset').value = data[2];
                document.getElementById('current_price').value = data[3];
                document.getElementById('add_price').value = data[4];

                document.getElementById('profit_asset').value = data[2];
                document.getElementById('profit_entry').value = data[3];
                document.getElementById('profit_price').value = data[4];
                
            } );
            
        } );
		</script>
	</head>
<body>

<?php

$pnl_color = $total_unrealized < 0 ? 'red' : 'green';

$exposure_color = 'green';

if ($exposure > 2) $exposure_color = 'red';
if ($exposure >= 1.5) $exposure_color = 'orange';

echo '<h1> KPI </h1>';

echo '<table class="table table-hover table-striped table-bordered" style="width:98%"><thead><th>Item</th><th>Value</th></tr></thead><tbody>';
echo '<tr><td>Exposure</td><td><strong  style="color : '.$exposure_color.'">'.$exposure.'</strong></td></tr>';
echo '<tr><td>Wallet</td><td><strong>$ '.number_format( $total_wallet , 2).'</strong></td></tr>';
echo '<tr><td>Unrealized PnL</td><td><strong style="color : '.$pnl_color.'">$ '.number_format( $total_unrealized , 2).'</strong></td></tr>';
echo '<tr><td>Total margin balance</td><td><strong>$ '.number_format( $total_margin_balance , 2).'</strong></td></tr>';
echo '<tr><td>Invested</td><td><strong>$ '.number_format( $invested , 2).'</strong></td></tr>';
echo '<tr><td>Current Worth</td><td><strong>$ '.number_format( $current_worth , 2).'</strong></td></tr>';
echo '<tr><td>Margin Ratio</td><td><strong>$ '.$margin_ratio.'</strong></td></tr>';
echo '<tr><td>Maintainance margin</td><td><strong>$ '.number_format( $total_maintainance_margin , 2).'</strong></td></tr>';
echo '</tbody></table>';

echo '<h1> Trades </h1>';

echo '<table id="trades" class="table table-hover table-striped table-bordered" style="width:98%"><thead><tr><th></th><th>Symbol</th><th>Total asset</th><th>Entry price</th><th>Current price</th><th>Invested</th><th>Current Worth</th><th>Profit</th><th>PnL</th></tr></thead><tbody>';

foreach($open_positions as $open_position) {

    $pnl_color = $open_position['pnl'] < 0 ? 'red' : 'green';

    echo '<tr>';
    echo '<td></td>';
    echo '<td>'.$open_position['symbol'].'</td>';
    echo '<td>'.$open_position['totalAsset'].'</td>';
    echo '<td>'.number_format($open_position['entryPrice'] , 6 , '.' , '').'</td>';
    echo '<td>'.number_format($open_position['currentPrice'] , 6 , '.' , '').'</td>';
    echo '<td>'.number_format($open_position['investedWorth'] , 6).'</td>';
    echo '<td>'.number_format($open_position['currentWorth'] , 6).'</td>';
    echo '<td style="color : '.$pnl_color.'">'.number_format($open_position['profitPercentage_min'] , 2).'%</td>';
    echo '<td style="color : '.$pnl_color.'">'.number_format( $open_position['pnl'] , 2).'</td>';
    echo '</tr>'; 

}

echo '</tbody></table>';

?>

<h1> DCA Calulator </h1>

Calculate the new avg DCA price. Select an row from the trades table to insert values. Add the new assets and price to calculate the new average price <br />

<input size="8" type="hidden" id="invested" value="<?php echo ($invested + $total_maintainance_margin); ?>">
<input size="8" type="hidden" id="total_wallet" value="<?php echo $total_wallet; ?>">

<table id="dca_calc" class="table table-hover table-striped table-bordered" style="width:98%">
    <thead>
        <tr>
            <th>...</th>
            <th>Assets</th>
            <th>Price</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td> Current </td>
            <td> <input size="8" type="text" id="current_asset"> </td>
            <td> <input size="8" type="text" id="current_price"> </td>
        </tr>
        <tr>
            <td> DCA </td>
            <td> <input size="8" type="text" id="add_asset"> </td>
            <td> <input size="8" type="text" id="add_price"> </td>
        </tr>
        <tr>
            <td> Avg. Price </td>
            <td> <input type="button" onclick="calculate()" value="Calculate" /> </td>
            <td> <input size="8" type="text" id="result"> </td>
        </tr>
    </tbody>
</table>

<div id="exposure_dca_result" style="display : none;"> Your new exposure will be approx. :</div>

<h1> Profit Calulator </h1>

Calculate the profit by setting the total assets , entry price and the take profit price. Profits are calculated without fees. <br />

<table id="profit_calc" class="table table-hover table-striped table-bordered" style="width:98%">
    <thead>
        <tr>
            <th>...</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td> Current assets </td>
            <td> <input size="8" type="text" id="profit_asset"> </td>
        </tr>
        <tr>
            <td> Entry price </td>
            <td> <input size="8" type="text" id="profit_entry"> </td>
        </tr>
        <tr>
            <td> Profit price </td>
            <td> <input size="8" type="text" id="profit_price"> </td>
        </tr>
        <tr>
            <td>&NonBreakingSpace;</td>
            <td> <input type="button" onclick="calculate_profit()" value="Calculate" /> </td>
        </tr>
        
    </tbody>
</table>

<div id="profit_result" style="display : none;"></div>

<?php

echo '<h1> Realized PnL</h1>';

echo 'Overview of your realized PnL over the last 7 days with an max of 1000 trades <br />';

echo '<table id="realized_pnl" class="table table-hover table-striped table-bordered" style="width:98%"><thead><tr><th>Date</th><th>Symbol</th><th>Realized PnL</th></tr></thead><tbody>';

foreach($income as $inc) {

    $pnl_color = $inc['income'] < 0 ? 'red' : 'green';

    echo '<tr>';
    echo '<td>'.date('Y-m-d H:i:s', $inc['time'] / 1000).'</td>';
    echo '<td>'.$inc['symbol'].'</td>';
    echo '<td style="color : '.$pnl_color.'">'.number_format( $inc['income'] , 2).'</td>';
    echo '</tr>'; 

}

echo '</tbody></table>';

?>

<h1> Trading view BTC </h1>

<!-- TradingView Widget BEGIN -->
<div class="tradingview-widget-container">
  <div id="tradingview_1d470" style="height : 600px;  width: 98%"></div>

  <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
  <script type="text/javascript">
  new TradingView.widget(
	{
	  "autosize": true,
	  "symbol": "BINANCE:BTCUSDTPERP",
	  "interval": "5",
	  "timezone": "Etc/UTC",
	  "theme": "light",
	  "style": "1",
	  "locale": "en",
	  "toolbar_bg": "#f1f3f6",
	  "enable_publishing": false,
	  "allow_symbol_change": true,
	  "container_id": "tradingview_1d470"
	}
  );
  </script>
</div>
<!-- TradingView Widget END -->
</body>
</html>