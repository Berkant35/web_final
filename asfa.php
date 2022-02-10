<?php

namespace App\Http\Controllers;

use App\EpcTags;
use App\Http\Requests\SetRequest;
use App\Items;
use App\Sets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use MongoDB\Driver\Session;
use function Symfony\Component\Translation\t;

class SetController extends Controller
{

    public function index()
    {
        $setList = Sets::all();
        return view('modules.set.index', compact('setList'));
    }

    public function setSession($setId)
    {
        \session()->put('setId', $setId);
        \session()->save();

        return redirect(route('create'));
    }

    public function setEdit($setId)
    {
        $setInfo = Sets::find($setId);
        if ($setInfo) {
            $setInfo = $setInfo->first();
            return view('modules.set.edit', compact('setInfo'));
        }else {
            return redirect(route('set.list'));
        }
    }

    public function setUpdate($setId, SetRequest $request)
    {
        $setUpdate = Sets::find($setId)->first();
        $setUpdate->set_name = $request->set_name;
        $setUpdate->set_description = $request->set_description;
        $setUpdate->save();

        if ($setUpdate) {
            return redirect(route('set.list'));
        }
    }

    public function setCreate()
    {
        return view('modules.set.create');
    }

    public function setStore(SetRequest $request)
    {
        $newSet = new Sets();
        $newSet->set_name = $request->set_name;
        $newSet->set_description = $request->set_description;
        $newSet->save();

        if ($newSet) {
            return redirect(route('set.list'))
                ->withSuccess('Set başarılı bir şekilde oluşturuldu.');
        }
    }

    public function itemCreate()
    {
        return view('modules.epc.create');
    }

    public function setDetail($setId)
    {
        $setName = Sets::find($setId)->set_name;
        $setInfo = Items::where('set_id', $setId)->get();
        if (true) {
            return view('modules.epc.detail', compact('setId', 'setInfo', 'setName'));
        }else {
            return redirect(route('set.list'));
        }
    }

    public function charTo6BitBinary($char)
    {
        if (strlen($char) != 1) {
            dd('charTo6Bit => tek karakter gelmedi');
        }

        $asciiCode = ord($char);

        $result = 0;
        if ($asciiCode >= 0x41 and $asciiCode <= 0x5F) {
            $result = $asciiCode & 0x3F;
        }else if ($asciiCode >= 0x20 and $asciiCode <= 0x3F) {
            $result = $asciiCode;
        }else {
            dd('charTo6Bit => invalid');
        }
        return str_pad(decbin($result), 6,'0', STR_PAD_LEFT);
    }

    public function binHex($value)
    {
        return dechex(bindec($value));
    }


    private function construct1EpcCreate($data = array(), $constructType)
    {
        if (isset($data['mfr']) and isset($data['ser'])) {

            $mfr = $data['mfr'];
            $ser = $data['ser'];

            $epcBin = '00111011'; // EPC header
            $epcBin .= '001111'; // EPC filter

// mfr kontrolü
            if (strlen($mfr) == 5) {
                $epcBin .= decbin(0x20);

                $mfrCharArray = str_split($mfr);

                foreach ($mfrCharArray as $char) {
                    $epcBin .= $this->charTo6BitBinary($char);
                }

            }else {
                dd('construct1EpcCreate => invalid mfr');
            }

// ser kontrolü
            $epcBin .= '000000';

//Burdaki operand ve olması gerekmiyor mu

            if (strlen($ser) > 0 || strlen($ser) <= 30) {

                $serCharArray = str_split($ser);

                foreach ($serCharArray as $char) {
                    $epcBin .= $this->charTo6BitBinary($char);
                }

            }else {
                dd('construct1EpcCreate => invalid serial');
            }

            $epcBin .= '000000';

            $epcBin .= '000000';

//dd($epcBin);

            $mod8 = strlen($epcBin)%8;
            $shiftValue = 0;

            if ($mod8 > 0) {
                $shiftValue = 8-$mod8;
            }

            $padValue = '';
            for ($i=0;$i<$shiftValue;$i++) {
                $padValue .= '0';
            }

//dd($epcBin . $padValue);
            $epcBin .= $padValue;

            $epc = '';
            for ($i=0;$i<strlen($epcBin);$i=$i+16) {
                $epc .= str_pad($this->binHex(substr($epcBin,$i,16)), 4, '0', STR_PAD_LEFT);
            }

            $userDataResult = $this->userDataCreate($data, $constructType);
//return strtoupper($epc);
            return array(
                'epc' => strtoupper($epc),
                'user_data' => $userDataResult['user_data'],
                'user_data_splited' => $userDataResult['user_data_splited'],
            );
        }else {
            return false;
        }
    }

    private function construct2EpcCreate($data = array(), $constructType)
    {
        if (isset($data['mfr']) and isset($data['pno']) and isset($data['seq'])) {
            $mfr = $data['mfr'];
            $seq = $data['seq'];
            $pno = $data['pno'];

            $epcBin = '00111011'; // EPC header
            $epcBin .= '001111'; // EPC filter

// mfr kontrolü
            if (strlen($mfr) == 5) {
                $epcBin .= decbin(0x20);

                $mfrCharArray = str_split($mfr);

                foreach ($mfrCharArray as $char) {
                    $epcBin .= $this->charTo6BitBinary($char);
                }

            }else {
                dd('construct2EpcCreate => invalid mfr');
            }

// pno kontrolü

            if (strlen($pno) > 0 || strlen($pno) <= 32) {

                $pnoCharArray = str_split($pno);

                foreach ($pnoCharArray as $char) {
                    $epcBin .= $this->charTo6BitBinary($char);
                }

            }else {
                dd('construct2EpcCreate => invalid pno');
            }

            $epcBin .= '000000';

// seq kontrolü
            if (strlen($seq) > 0 || strlen($seq) <= 30) {

                $seqCharArray = str_split($seq);

                foreach ($seqCharArray as $char) {
                    $epcBin .= $this->charTo6BitBinary($char);
                }

            }else {
                dd('construct2EpcCreate => invalid seq');
            }

            $epcBin .= '000000';

//dd($epcBin);

            $mod8 = strlen($epcBin)%8;
            $shiftValue = 0;

            if ($mod8 > 0) {
                $shiftValue = 8-$mod8;
            }

            $padValue = '';
            for ($i=0;$i<$shiftValue;$i++) {
                $padValue .= '0';
            }

//dd($epcBin . $padValue);
            $epcBin .= $padValue;

            $epc = '';
            for ($i=0;$i<strlen($epcBin);$i=$i+16) {
                $epc .= str_pad($this->binHex(substr($epcBin,$i,16)), 4, '0', STR_PAD_LEFT);
            }

//return strtoupper($epc);

            $userDataResult = $this->userDataCreate($data, $constructType);

            return array(
                'epc' => strtoupper($epc),
                'user_data' => $userDataResult['user_data'],
                'user_data_splited' => $userDataResult['user_data_splited'],
            );
        }else {
            return false;
        }
    }

    public function userDataCreate($requestData = array(), $constructType)
    {
        $dataHeader = '0001111000000000'; // DSFID 0x1E00
        $dataHeader .= '0110010000100001'; // TOC version, tag type, ATACLASS
        $dataHeader .= '0000000001000000'; // FLags, size of TOC header, size of RDs

        $dataPayload = '';

        $tmp  = '';
        if ($constructType != 'construct_2') {
            $tmp .= 'MFR ' . $requestData['mfr'];
        }

        if (isset($requestData['ser']) and strlen($requestData['ser']) > 0) {
            $tmp .= '*' . 'SER ' . $requestData['ser'];
        }else if ($constructType != 'construct_2' and isset($requestData['seq']) and strlen($requestData['seq']) > 0) {
            $tmp .= '*' . 'SEQ ' . $requestData['seq'];
        }

        if (isset($requestData['pnr']) and strlen($requestData['pnr']) > 0) {
            $tmp .= '*' . 'PNR ' . $requestData['pnr'];
        }
        if (isset($requestData['dmf']) and strlen($requestData['dmf']) > 0) {
            $tmp .= '*' . 'DMF ' . $requestData['dmf'];
        }

        if (isset($requestData['exp']) and strlen($requestData['exp']) > 0) {
            $tmp .= '*LLE 1*EXP ' . $requestData['exp'];
        }
        if ($constructType != 'construct_2' and isset($requestData['pno']) and strlen($requestData['pno']) > 0) {
            $tmp .= '*' . 'PNO ' . $requestData['pno'];
        }
        // tmp .= '000000'
        if (substr($tmp, 0, strlen("*")) == "*") {
            $tmp = substr($tmp, strlen("*"));
            //tmp  = *;
        }

        $payloadLen = strlen($tmp);
        $payloadLen = ($payloadLen*6)+6;

        $payloadWordLen = ceil($payloadLen/16);

        $userDataWordLen = $payloadWordLen+4;

        $dataHeader .= str_pad(decbin($userDataWordLen), 16, '0', STR_PAD_LEFT);


        $dataPayload .= $dataHeader;

        $tmpCharArray = str_split($tmp);

        foreach ($tmpCharArray as $char) {
            $dataPayload .= $this->charTo6BitBinary($char);
        }

        $dataPayload .= '000000';

        $mod8 = strlen($dataPayload)%8;
        $shiftValue = 0;

        if ($mod8 > 0) {
            $shiftValue = 8-$mod8;
        }

        $padValue = '';
        for ($i=0;$i<$shiftValue;$i++) {
            $padValue .= '0';
        }

//dd($epcBin . $padValue);
        $dataPayload .= $padValue;

        $user_data = '';
        for ($i=0;$i<strlen($dataPayload);$i=$i+16) {
            $user_data .= str_pad($this->binHex(substr($dataPayload,$i,16)), 4, '0', STR_PAD_LEFT);
        }


        $user_data = strtoupper($user_data);


        $userDataArray = array();
        $aArray = str_split($user_data, 16);

        foreach ($aArray as $a) {
            $aArray2 = str_split($a, 4);
            $userDataArray[] = implode(' ', $aArray2);
        }

        return array(
            'user_data' => $user_data,
            'user_data_splited' => $userDataArray
        );


//dd($requestData, $dataPayload);

    }


    public function allPrint(Request $request)
    {
        $setInfo = Sets::find($request->set_id);

        $printList = array();
        if ($setInfo) {
            foreach ($setInfo->items as $item) {

                if ($item->tags->first()->encoded == 0) {
                    $printList[] = array(
                        'epc' => $item->tags->first()->epc,
                        'user_data' => $item->tags->first()->user_data,
                        'details' => $item->toArray()
                    );

                    $item->tags->first()->encoded = 1;
                    $item->tags->first()->save();
                }

                /*if ($this->epcSatoPrinter($item->tags->first()->epc, $item->tags->first()->user_data)) {
                                    $item->tags->first()->encoded = 1;
                                    $item->tags->first()->save();

                                    return true;
                                }else {
                                    return false;
                                }*/
            }

            $this->allEpcSatoPrinter($printList);
//sleep(count($printList)*2);
            return true;
        }else {
            return false;
        }
    }

    public function withIdEpcPrint(Request $request)
    {
        $tagControl = EpcTags::find($request->tag_id);
        if ($tagControl) {
            if ($this->epcSatoPrinter($tagControl->epc, $tagControl->user_data, $tagControl->item->first()->toArray())) {
                $tagControl->encoded = 1;
                $tagControl->save();

                return true;
            }
        }else {
            return false;
        }
    }

    public function testThyPrint(Request $request)
    {
        $tagControl = EpcTags::find($request->tag_id);
        if ($tagControl) {
            $epc = $tagControl->first()->epc;

            $imp = '^XA
^FO50,10^GFA,6750,6750,45,,::::::::::::::::::::::::::::::::::V07FF8,U07JFC,T03LF8,S01FEJ0FF,S07EK01FCP07KF83F8003F01IFEI03F801FE03F800IF003F8003F8,R01F8L03EP07KF83F8003F01JFC003F801FC07F803IFC03F8003F8,R03EN0F8O07KF83F8003F01JFE003F803F807F807IFC03F8003F8,R0FFN03EO07KF83F8003F01KF003F807F007F80JFC03F8003F8,Q01FFCN0FO07KF83F8003F01KF803F80FF007F80JFC03F8003F8,Q0787FN078N07KF03F8003F01KFC03F80FE007F81FF07C03F8003F8,Q0F03FEM01CP07F8003F8003F01FC07FC03F81FC007F81FC00403F8003F8,P01E01FF8M0EP07F8003F8003F01FC03FC03F83F8007F81FCJ03F8003F8,P03C01FFEM07P07F8003F8003F01FC01FC03F87F8007F81FCJ03F8003F8,P07800IFM038O07F8003F8003F01FC01FC03F87FI07F81FCJ03F8003F8,P07I07FFCL01CO07F8003F8003F01FC01FC03F8FEI07F81FEJ03F8003F8,P0EI03FFEM0EO07F8003F8003F01FC01FC03F9FCI07F81FFJ03F8003F8,O01CI03IF8L07O07F8003F8003F01FC03FC03FBF8I07F81FFEI03F8007F8,O038I01IFCL07O07F8003F8003F01FC03F803IF8I07F80IF8003LF8,O078I01IFEL038N07F8003F8003F01KF803IFJ07F807FFE003LF8,O07J01JFL01CN07F8003F8003F01KF003IF8I07F803IF803LF8,O0EK0JF8K01CN07F8003F8003F01JFE003IFCI07F801IFC03LF8,O0EK0JFCL0EN07F8003F8003F01JFC003IFEI07F8007FFC03LF8,N01CK0JFCL06N07F8003F8003F01JF8003F9FEI07F8001FFE03F8003F8,N01CK07IFEL07N07F8003F8003F01FC3FC003F9FFI07F8I03FE03F8003F8,N038K07JFL03N07F8003F8003F01FC1FE003F8FF8007F8I01FE03F8003F8,N038K07JFL038M07F8003FC007F01FC1FE003F87FC007F8J0FE03F8003F8,N03L07JF8K038M07F8003FC007F01FC0FF003F83FC007F8J0FF03F8003F8,N07L03JF8K018M07F8001FC007F01FC0FF803F81FE007F8J0FE03F8003F8,N07L03JFCK01CM07F8001FE00FF01FC07F803F81FF007F8J0FE03F8003F8,N06L03JFCK01CM07F8001FF01FE01FC03FC03F80FF807F81C01FE03F8003F8,N0EL03JFCL0CM07F8I0KFE01FC03FC03F807FC07F81JFE03F8003F8,N0EL03JFCL0CM07F8I0KFC01FC01FE03F803FC07F81JFC03F8003F8,N0CL03JFCL0EM07F8I07JFC01FC01FF03F803FE07F81JF803F8003F8,N0CL03JFCL0EM07F8I03JF001FC00FF03F801FF07F81JF003F8003F8,N0CL03JFCL06M07F8J0IFE001FC003F83F800FF87F80IFE003F8003F8,N0CL03JFCL06T01FEgJ0FF,M01CL03JFCL06,M01CL07JFCL06,M01CL07JF8L06,:M01CL07JFM06,:M01CL0JFEM06,M01CL0JFCM06,N0CL0JF8I0FFC06gH0F8gL01E,N0CK01PF006K07KF81JFCI0IF807FI07F807FI03F00FEI01IF,N0CK01OFI0EK07KF81JFE003IFC07FI07F80FF8003F00FEI07IF8,N0CK03NF8I0EK07KF81JFE007IFC07FI07F80FFC003F00FE001JF8,N0EK03MF8J0CK07KF81JFC00JFC07FI07F80FFE003F00FE003JF8,N0EK07LFCK0CK07KF81JFC01JFC07FI07F80FFE003F00FE007JF8,N06K0LFCK01CK07JFE01JF803FF83C07FI07F80IF003F00FE007FE078,N06J01KFEL01CM07F8001FCJ07FC00407FI07F80IF803F00FE00FF8,N07J03JFEM018M07F8001FCJ07F8J07FI07F80IF803F00FE01FF,N07J07JFN038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FE,N03J0JFO038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FC,N038003IF8O03N07F8001FCJ0FEK07FI07F80IFE03F00FE03FC,N018007FF8P07N07F8001FCI01FEK07FI07F80FEFF03F00FE03FC,N01C01FFCQ06N07F8001FCI01FEK07F8007F80FEFF03F00FE03F8,O0C07FCR0EN07F8001JF81FEK07LF80FE7F83F00FE03F8,O0E3FES0CN07F8001JF81FEK07LF80FE3FC3F00FE03F8,O07FFS01CN07F8001JF81FCK07LF80FE3FC3F00FE03F8,O07FT038N07F8001JF81FCK07LF80FE1FE3F00FE03F8,O038T038N07F8001JF01FEK07LF80FE0FE3F00FE03F8,O01CT07O07F8001FCI01FEK07F8007F80FE0FF3F00FE03F8,O01ET0EO07F8001FCI01FEK07FI07F80FE07F3F00FE03FC,P0ES01CO07F8001FCI01FEK07FI07F80FE07FBF00FE03FC,P07S03CO07F8001FCJ0FEK07FI07F80FE03IF00FE03FC,P038R078O07F8001FCJ0FFK07FI07F80FE01IF00FE01FE,P01CR0FP07F8001FCJ0FF8J07FI07F80FE01IF00FE01FE,Q0FQ01EP07F8001FCJ07FCJ07FI07F80FE00IF00FE01FF8,Q078P03CP07F8001JFC07FF01C07FI07F80FE007FF00FE00FFE038,Q03CP0FQ07F8001JFE03JFC07FI07F80FE007FF00FE007JF8,R0FO01EQ07F8001JFE01JFC07FI07F80FE003FF00FE003JF8,R07CN07CQ07F8001JFE00JFC07FI07F80FE003FF00FE001JF8,R03FM01FR07F8001JFE007IFC07FI07F80FE001FF00FEI0JF8,S0FCL0FCR07F8001JFE001IFC07FI07F80FEI0FF00FEI03IF8,S03F8J03F8gL03FFgL07FC,T0FFC007FC,T01KFE,U01IFE,,::::::::::::::::::::::::::::::::::^FS
^FO430,40
^BXN,4,200,,,,,2
^FDMFR D1379/SER ARYF-F001-213/PNR E63420^FS

^FO620,40^CFA,30^FDMFR D1379 - PNR E63420^FS
^FO620,75^CFA,30^FDSER ARYF-F001-213^FS
^FO620,110^CFA,30^FDDMF 20070601 - EXP 20270601^FS
^XZ';

            $address = '192.168.1.100';

            if ($address == '0.0.0.0'){
                $address = $_SERVER['REMOTE_ADDR'];
            }

            $port = '9100';

            try {
                $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                $sockconnect = socket_connect($sock, $address, $port);
                socket_write($sock, $imp, strlen($imp));
                socket_close($sock);
                return response()->json('{Status:Success}', 200);
            } catch (\Exception $e) {
                return response()->json('{Status:Error}', 500);
            }
        }else {
            return false;
        }
    }

    public function withIdEpcDelete(Request $request)
    {
        $tagControl = EpcTags::find($request->tag_id);
        if ($tagControl) {
            $itemId = $tagControl->item->first()->id;
            $tagControl->delete();

            $itemControl = Items::find($itemId);
            $itemControl->delete();

            return true;
        }else {
            return false;
        }
    }

    public function epcSatoPrinter($epc, $user_data, $details = array())
    {
        if (!empty($epc)) {

            $imp = '^XA';
            $imp .= '^RFW,H,2,,A^FD'. $epc .'^FS
^RFW,H,0,'. (strlen($user_data)/2) .',3^FD'. $user_data .'^FS';

            if (count($details) > 0) {
                if ($details['construct_type'] == 'construct_1') {
                    $imp .= '^FO50,10^GFA,6750,6750,45,,::::::::::::::::::::::::::::::::::V07FF8,U07JFC,T03LF8,S01FEJ0FF,S07EK01FCP07KF83F8003F01IFEI03F801FE03F800IF003F8003F8,R01F8L03EP07KF83F8003F01JFC003F801FC07F803IFC03F8003F8,R03EN0F8O07KF83F8003F01JFE003F803F807F807IFC03F8003F8,R0FFN03EO07KF83F8003F01KF003F807F007F80JFC03F8003F8,Q01FFCN0FO07KF83F8003F01KF803F80FF007F80JFC03F8003F8,Q0787FN078N07KF03F8003F01KFC03F80FE007F81FF07C03F8003F8,Q0F03FEM01CP07F8003F8003F01FC07FC03F81FC007F81FC00403F8003F8,P01E01FF8M0EP07F8003F8003F01FC03FC03F83F8007F81FCJ03F8003F8,P03C01FFEM07P07F8003F8003F01FC01FC03F87F8007F81FCJ03F8003F8,P07800IFM038O07F8003F8003F01FC01FC03F87FI07F81FCJ03F8003F8,P07I07FFCL01CO07F8003F8003F01FC01FC03F8FEI07F81FEJ03F8003F8,P0EI03FFEM0EO07F8003F8003F01FC01FC03F9FCI07F81FFJ03F8003F8,O01CI03IF8L07O07F8003F8003F01FC03FC03FBF8I07F81FFEI03F8007F8,O038I01IFCL07O07F8003F8003F01FC03F803IF8I07F80IF8003LF8,O078I01IFEL038N07F8003F8003F01KF803IFJ07F807FFE003LF8,O07J01JFL01CN07F8003F8003F01KF003IF8I07F803IF803LF8,O0EK0JF8K01CN07F8003F8003F01JFE003IFCI07F801IFC03LF8,O0EK0JFCL0EN07F8003F8003F01JFC003IFEI07F8007FFC03LF8,N01CK0JFCL06N07F8003F8003F01JF8003F9FEI07F8001FFE03F8003F8,N01CK07IFEL07N07F8003F8003F01FC3FC003F9FFI07F8I03FE03F8003F8,N038K07JFL03N07F8003F8003F01FC1FE003F8FF8007F8I01FE03F8003F8,N038K07JFL038M07F8003FC007F01FC1FE003F87FC007F8J0FE03F8003F8,N03L07JF8K038M07F8003FC007F01FC0FF003F83FC007F8J0FF03F8003F8,N07L03JF8K018M07F8001FC007F01FC0FF803F81FE007F8J0FE03F8003F8,N07L03JFCK01CM07F8001FE00FF01FC07F803F81FF007F8J0FE03F8003F8,N06L03JFCK01CM07F8001FF01FE01FC03FC03F80FF807F81C01FE03F8003F8,N0EL03JFCL0CM07F8I0KFE01FC03FC03F807FC07F81JFE03F8003F8,N0EL03JFCL0CM07F8I0KFC01FC01FE03F803FC07F81JFC03F8003F8,N0CL03JFCL0EM07F8I07JFC01FC01FF03F803FE07F81JF803F8003F8,N0CL03JFCL0EM07F8I03JF001FC00FF03F801FF07F81JF003F8003F8,N0CL03JFCL06M07F8J0IFE001FC003F83F800FF87F80IFE003F8003F8,N0CL03JFCL06T01FEgJ0FF,M01CL03JFCL06,M01CL07JFCL06,M01CL07JF8L06,:M01CL07JFM06,:M01CL0JFEM06,M01CL0JFCM06,N0CL0JF8I0FFC06gH0F8gL01E,N0CK01PF006K07KF81JFCI0IF807FI07F807FI03F00FEI01IF,N0CK01OFI0EK07KF81JFE003IFC07FI07F80FF8003F00FEI07IF8,N0CK03NF8I0EK07KF81JFE007IFC07FI07F80FFC003F00FE001JF8,N0EK03MF8J0CK07KF81JFC00JFC07FI07F80FFE003F00FE003JF8,N0EK07LFCK0CK07KF81JFC01JFC07FI07F80FFE003F00FE007JF8,N06K0LFCK01CK07JFE01JF803FF83C07FI07F80IF003F00FE007FE078,N06J01KFEL01CM07F8001FCJ07FC00407FI07F80IF803F00FE00FF8,N07J03JFEM018M07F8001FCJ07F8J07FI07F80IF803F00FE01FF,N07J07JFN038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FE,N03J0JFO038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FC,N038003IF8O03N07F8001FCJ0FEK07FI07F80IFE03F00FE03FC,N018007FF8P07N07F8001FCI01FEK07FI07F80FEFF03F00FE03FC,N01C01FFCQ06N07F8001FCI01FEK07F8007F80FEFF03F00FE03F8,O0C07FCR0EN07F8001JF81FEK07LF80FE7F83F00FE03F8,O0E3FES0CN07F8001JF81FEK07LF80FE3FC3F00FE03F8,O07FFS01CN07F8001JF81FCK07LF80FE3FC3F00FE03F8,O07FT038N07F8001JF81FCK07LF80FE1FE3F00FE03F8,O038T038N07F8001JF01FEK07LF80FE0FE3F00FE03F8,O01CT07O07F8001FCI01FEK07F8007F80FE0FF3F00FE03F8,O01ET0EO07F8001FCI01FEK07FI07F80FE07F3F00FE03FC,P0ES01CO07F8001FCI01FEK07FI07F80FE07FBF00FE03FC,P07S03CO07F8001FCJ0FEK07FI07F80FE03IF00FE03FC,P038R078O07F8001FCJ0FFK07FI07F80FE01IF00FE01FE,P01CR0FP07F8001FCJ0FF8J07FI07F80FE01IF00FE01FE,Q0FQ01EP07F8001FCJ07FCJ07FI07F80FE00IF00FE01FF8,Q078P03CP07F8001JFC07FF01C07FI07F80FE007FF00FE00FFE038,Q03CP0FQ07F8001JFE03JFC07FI07F80FE007FF00FE007JF8,R0FO01EQ07F8001JFE01JFC07FI07F80FE003FF00FE003JF8,R07CN07CQ07F8001JFE00JFC07FI07F80FE003FF00FE001JF8,R03FM01FR07F8001JFE007IFC07FI07F80FE001FF00FEI0JF8,S0FCL0FCR07F8001JFE001IFC07FI07F80FEI0FF00FEI03IF8,S03F8J03F8gL03FFgL07FC,T0FFC007FC,T01KFE,U01IFE,,::::::::::::::::::::::::::::::::::^FS
^FO430,40
^BXN,4,200,,,,,2
^FDMFR '. $details['mfr'] .'/SER '. $details['ser'] .'/PNR '. $details['pnr'] .'^FS

^FO620,40^CFA,30^FDMFR '. $details['mfr'] .' - PNR '. $details['pnr'] .'^FS
^FO620,75^CFA,30^FDSER '. $details['ser'] .'^FS
^FO620,110^CFA,30^FDDMF '. $details['dmf'] .' - EXP '. $details['exp'] .'^FS';
                }else if ($details['construct_type'] == 'construct_2') {
                    $imp .= '^FO50,10^GFA,6750,6750,45,,::::::::::::::::::::::::::::::::::V07FF8,U07JFC,T03LF8,S01FEJ0FF,S07EK01FCP07KF83F8003F01IFEI03F801FE03F800IF003F8003F8,R01F8L03EP07KF83F8003F01JFC003F801FC07F803IFC03F8003F8,R03EN0F8O07KF83F8003F01JFE003F803F807F807IFC03F8003F8,R0FFN03EO07KF83F8003F01KF003F807F007F80JFC03F8003F8,Q01FFCN0FO07KF83F8003F01KF803F80FF007F80JFC03F8003F8,Q0787FN078N07KF03F8003F01KFC03F80FE007F81FF07C03F8003F8,Q0F03FEM01CP07F8003F8003F01FC07FC03F81FC007F81FC00403F8003F8,P01E01FF8M0EP07F8003F8003F01FC03FC03F83F8007F81FCJ03F8003F8,P03C01FFEM07P07F8003F8003F01FC01FC03F87F8007F81FCJ03F8003F8,P07800IFM038O07F8003F8003F01FC01FC03F87FI07F81FCJ03F8003F8,P07I07FFCL01CO07F8003F8003F01FC01FC03F8FEI07F81FEJ03F8003F8,P0EI03FFEM0EO07F8003F8003F01FC01FC03F9FCI07F81FFJ03F8003F8,O01CI03IF8L07O07F8003F8003F01FC03FC03FBF8I07F81FFEI03F8007F8,O038I01IFCL07O07F8003F8003F01FC03F803IF8I07F80IF8003LF8,O078I01IFEL038N07F8003F8003F01KF803IFJ07F807FFE003LF8,O07J01JFL01CN07F8003F8003F01KF003IF8I07F803IF803LF8,O0EK0JF8K01CN07F8003F8003F01JFE003IFCI07F801IFC03LF8,O0EK0JFCL0EN07F8003F8003F01JFC003IFEI07F8007FFC03LF8,N01CK0JFCL06N07F8003F8003F01JF8003F9FEI07F8001FFE03F8003F8,N01CK07IFEL07N07F8003F8003F01FC3FC003F9FFI07F8I03FE03F8003F8,N038K07JFL03N07F8003F8003F01FC1FE003F8FF8007F8I01FE03F8003F8,N038K07JFL038M07F8003FC007F01FC1FE003F87FC007F8J0FE03F8003F8,N03L07JF8K038M07F8003FC007F01FC0FF003F83FC007F8J0FF03F8003F8,N07L03JF8K018M07F8001FC007F01FC0FF803F81FE007F8J0FE03F8003F8,N07L03JFCK01CM07F8001FE00FF01FC07F803F81FF007F8J0FE03F8003F8,N06L03JFCK01CM07F8001FF01FE01FC03FC03F80FF807F81C01FE03F8003F8,N0EL03JFCL0CM07F8I0KFE01FC03FC03F807FC07F81JFE03F8003F8,N0EL03JFCL0CM07F8I0KFC01FC01FE03F803FC07F81JFC03F8003F8,N0CL03JFCL0EM07F8I07JFC01FC01FF03F803FE07F81JF803F8003F8,N0CL03JFCL0EM07F8I03JF001FC00FF03F801FF07F81JF003F8003F8,N0CL03JFCL06M07F8J0IFE001FC003F83F800FF87F80IFE003F8003F8,N0CL03JFCL06T01FEgJ0FF,M01CL03JFCL06,M01CL07JFCL06,M01CL07JF8L06,:M01CL07JFM06,:M01CL0JFEM06,M01CL0JFCM06,N0CL0JF8I0FFC06gH0F8gL01E,N0CK01PF006K07KF81JFCI0IF807FI07F807FI03F00FEI01IF,N0CK01OFI0EK07KF81JFE003IFC07FI07F80FF8003F00FEI07IF8,N0CK03NF8I0EK07KF81JFE007IFC07FI07F80FFC003F00FE001JF8,N0EK03MF8J0CK07KF81JFC00JFC07FI07F80FFE003F00FE003JF8,N0EK07LFCK0CK07KF81JFC01JFC07FI07F80FFE003F00FE007JF8,N06K0LFCK01CK07JFE01JF803FF83C07FI07F80IF003F00FE007FE078,N06J01KFEL01CM07F8001FCJ07FC00407FI07F80IF803F00FE00FF8,N07J03JFEM018M07F8001FCJ07F8J07FI07F80IF803F00FE01FF,N07J07JFN038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FE,N03J0JFO038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FC,N038003IF8O03N07F8001FCJ0FEK07FI07F80IFE03F00FE03FC,N018007FF8P07N07F8001FCI01FEK07FI07F80FEFF03F00FE03FC,N01C01FFCQ06N07F8001FCI01FEK07F8007F80FEFF03F00FE03F8,O0C07FCR0EN07F8001JF81FEK07LF80FE7F83F00FE03F8,O0E3FES0CN07F8001JF81FEK07LF80FE3FC3F00FE03F8,O07FFS01CN07F8001JF81FCK07LF80FE3FC3F00FE03F8,O07FT038N07F8001JF81FCK07LF80FE1FE3F00FE03F8,O038T038N07F8001JF01FEK07LF80FE0FE3F00FE03F8,O01CT07O07F8001FCI01FEK07F8007F80FE0FF3F00FE03F8,O01ET0EO07F8001FCI01FEK07FI07F80FE07F3F00FE03FC,P0ES01CO07F8001FCI01FEK07FI07F80FE07FBF00FE03FC,P07S03CO07F8001FCJ0FEK07FI07F80FE03IF00FE03FC,P038R078O07F8001FCJ0FFK07FI07F80FE01IF00FE01FE,P01CR0FP07F8001FCJ0FF8J07FI07F80FE01IF00FE01FE,Q0FQ01EP07F8001FCJ07FCJ07FI07F80FE00IF00FE01FF8,Q078P03CP07F8001JFC07FF01C07FI07F80FE007FF00FE00FFE038,Q03CP0FQ07F8001JFE03JFC07FI07F80FE007FF00FE007JF8,R0FO01EQ07F8001JFE01JFC07FI07F80FE003FF00FE003JF8,R07CN07CQ07F8001JFE00JFC07FI07F80FE003FF00FE001JF8,R03FM01FR07F8001JFE007IFC07FI07F80FE001FF00FEI0JF8,S0FCL0FCR07F8001JFE001IFC07FI07F80FEI0FF00FEI03IF8,S03F8J03F8gL03FFgL07FC,T0FFC007FC,T01KFE,U01IFE,,::::::::::::::::::::::::::::::::::^FS
^FO430,40
^BXN,4,200,,,,,2
^FDMFR '. $details['mfr'] .'/SEQ '. $details['seq'] .'/PNO '. $details['pno'] .'^FS

^FO620,40^CFA,30^FDMFR '. $details['mfr'] .' - PNO '. $details['pno'] .'^FS
^FO620,75^CFA,30^FDSEQ '. $details['seq'] .'^FS
^FO620,110^CFA,30^FDDMF '. $details['dmf'] .' - EXP '. $details['exp'] .'^FS';
                }
            }

            $imp .= '^XZ';


// Orjinal RFID yazmak için.
            /*$imp = '^XA
                        ^RFW,H,2,,A^FD'. $epc .'^FS
                        ^RFW,H,0,'. (strlen($user_data)/2) .',3^FD'. $user_data .'^FS
                        ^XZ';*/

//^RFW,H,0,68,3^FD1E0064210040002A3464A0C72CF4D6A4C54A00420F1CB3A90392805DB3D32C2DC30A84346832C31C31C31C2A30C160C6A158420CB0CB0C70C70A9038F834D33D33D40000^FS

            $address = '192.168.1.100';

            if ($address == '0.0.0.0'){
                $address = $_SERVER['REMOTE_ADDR'];
            }

            $port = '9100';

            try {
                $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                $sockconnect = socket_connect($sock, $address, $port);
                socket_write($sock, $imp, strlen($imp));
                socket_close($sock);

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function allEpcSatoPrinter($lists = array())
    {
        if (count($lists) > 0) {

            $imp = '';
            foreach ($lists as $list) {
                $imp .= '^XA';
                $imp .= '^RFW,H,2,,A^FD'. $list['epc'] .'^FS
^RFW,H,0,'. (strlen($list['user_data'])/2) .',3^FD'. $list['user_data'] .'^FS';

                if (count($list['details']) > 0) {
                    if ($list['details']['construct_type'] == 'construct_1') {
                        $imp .= '^FO50,10^GFA,6750,6750,45,,::::::::::::::::::::::::::::::::::V07FF8,U07JFC,T03LF8,S01FEJ0FF,S07EK01FCP07KF83F8003F01IFEI03F801FE03F800IF003F8003F8,R01F8L03EP07KF83F8003F01JFC003F801FC07F803IFC03F8003F8,R03EN0F8O07KF83F8003F01JFE003F803F807F807IFC03F8003F8,R0FFN03EO07KF83F8003F01KF003F807F007F80JFC03F8003F8,Q01FFCN0FO07KF83F8003F01KF803F80FF007F80JFC03F8003F8,Q0787FN078N07KF03F8003F01KFC03F80FE007F81FF07C03F8003F8,Q0F03FEM01CP07F8003F8003F01FC07FC03F81FC007F81FC00403F8003F8,P01E01FF8M0EP07F8003F8003F01FC03FC03F83F8007F81FCJ03F8003F8,P03C01FFEM07P07F8003F8003F01FC01FC03F87F8007F81FCJ03F8003F8,P07800IFM038O07F8003F8003F01FC01FC03F87FI07F81FCJ03F8003F8,P07I07FFCL01CO07F8003F8003F01FC01FC03F8FEI07F81FEJ03F8003F8,P0EI03FFEM0EO07F8003F8003F01FC01FC03F9FCI07F81FFJ03F8003F8,O01CI03IF8L07O07F8003F8003F01FC03FC03FBF8I07F81FFEI03F8007F8,O038I01IFCL07O07F8003F8003F01FC03F803IF8I07F80IF8003LF8,O078I01IFEL038N07F8003F8003F01KF803IFJ07F807FFE003LF8,O07J01JFL01CN07F8003F8003F01KF003IF8I07F803IF803LF8,O0EK0JF8K01CN07F8003F8003F01JFE003IFCI07F801IFC03LF8,O0EK0JFCL0EN07F8003F8003F01JFC003IFEI07F8007FFC03LF8,N01CK0JFCL06N07F8003F8003F01JF8003F9FEI07F8001FFE03F8003F8,N01CK07IFEL07N07F8003F8003F01FC3FC003F9FFI07F8I03FE03F8003F8,N038K07JFL03N07F8003F8003F01FC1FE003F8FF8007F8I01FE03F8003F8,N038K07JFL038M07F8003FC007F01FC1FE003F87FC007F8J0FE03F8003F8,N03L07JF8K038M07F8003FC007F01FC0FF003F83FC007F8J0FF03F8003F8,N07L03JF8K018M07F8001FC007F01FC0FF803F81FE007F8J0FE03F8003F8,N07L03JFCK01CM07F8001FE00FF01FC07F803F81FF007F8J0FE03F8003F8,N06L03JFCK01CM07F8001FF01FE01FC03FC03F80FF807F81C01FE03F8003F8,N0EL03JFCL0CM07F8I0KFE01FC03FC03F807FC07F81JFE03F8003F8,N0EL03JFCL0CM07F8I0KFC01FC01FE03F803FC07F81JFC03F8003F8,N0CL03JFCL0EM07F8I07JFC01FC01FF03F803FE07F81JF803F8003F8,N0CL03JFCL0EM07F8I03JF001FC00FF03F801FF07F81JF003F8003F8,N0CL03JFCL06M07F8J0IFE001FC003F83F800FF87F80IFE003F8003F8,N0CL03JFCL06T01FEgJ0FF,M01CL03JFCL06,M01CL07JFCL06,M01CL07JF8L06,:M01CL07JFM06,:M01CL0JFEM06,M01CL0JFCM06,N0CL0JF8I0FFC06gH0F8gL01E,N0CK01PF006K07KF81JFCI0IF807FI07F807FI03F00FEI01IF,N0CK01OFI0EK07KF81JFE003IFC07FI07F80FF8003F00FEI07IF8,N0CK03NF8I0EK07KF81JFE007IFC07FI07F80FFC003F00FE001JF8,N0EK03MF8J0CK07KF81JFC00JFC07FI07F80FFE003F00FE003JF8,N0EK07LFCK0CK07KF81JFC01JFC07FI07F80FFE003F00FE007JF8,N06K0LFCK01CK07JFE01JF803FF83C07FI07F80IF003F00FE007FE078,N06J01KFEL01CM07F8001FCJ07FC00407FI07F80IF803F00FE00FF8,N07J03JFEM018M07F8001FCJ07F8J07FI07F80IF803F00FE01FF,N07J07JFN038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FE,N03J0JFO038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FC,N038003IF8O03N07F8001FCJ0FEK07FI07F80IFE03F00FE03FC,N018007FF8P07N07F8001FCI01FEK07FI07F80FEFF03F00FE03FC,N01C01FFCQ06N07F8001FCI01FEK07F8007F80FEFF03F00FE03F8,O0C07FCR0EN07F8001JF81FEK07LF80FE7F83F00FE03F8,O0E3FES0CN07F8001JF81FEK07LF80FE3FC3F00FE03F8,O07FFS01CN07F8001JF81FCK07LF80FE3FC3F00FE03F8,O07FT038N07F8001JF81FCK07LF80FE1FE3F00FE03F8,O038T038N07F8001JF01FEK07LF80FE0FE3F00FE03F8,O01CT07O07F8001FCI01FEK07F8007F80FE0FF3F00FE03F8,O01ET0EO07F8001FCI01FEK07FI07F80FE07F3F00FE03FC,P0ES01CO07F8001FCI01FEK07FI07F80FE07FBF00FE03FC,P07S03CO07F8001FCJ0FEK07FI07F80FE03IF00FE03FC,P038R078O07F8001FCJ0FFK07FI07F80FE01IF00FE01FE,P01CR0FP07F8001FCJ0FF8J07FI07F80FE01IF00FE01FE,Q0FQ01EP07F8001FCJ07FCJ07FI07F80FE00IF00FE01FF8,Q078P03CP07F8001JFC07FF01C07FI07F80FE007FF00FE00FFE038,Q03CP0FQ07F8001JFE03JFC07FI07F80FE007FF00FE007JF8,R0FO01EQ07F8001JFE01JFC07FI07F80FE003FF00FE003JF8,R07CN07CQ07F8001JFE00JFC07FI07F80FE003FF00FE001JF8,R03FM01FR07F8001JFE007IFC07FI07F80FE001FF00FEI0JF8,S0FCL0FCR07F8001JFE001IFC07FI07F80FEI0FF00FEI03IF8,S03F8J03F8gL03FFgL07FC,T0FFC007FC,T01KFE,U01IFE,,::::::::::::::::::::::::::::::::::^FS
^FO430,40
^BXN,4,200,,,,,2
^FDMFR '. $list['details']['mfr'] .'/SER '. $list['details']['ser'] .'/PNR '. $list['details']['pnr'] .'^FS

^FO620,40^CFA,30^FDMFR '. $list['details']['mfr'] .' - PNR '. $list['details']['pnr'] .'^FS
^FO620,75^CFA,30^FDSER '. $list['details']['ser'] .'^FS
^FO620,110^CFA,30^FDDMF '. $list['details']['dmf'] .' - EXP '. $list['details']['exp'] .'^FS';
                    }else if ($list['details']['construct_type'] == 'construct_2') {
                        $imp .= '^FO50,10^GFA,6750,6750,45,,::::::::::::::::::::::::::::::::::V07FF8,U07JFC,T03LF8,S01FEJ0FF,S07EK01FCP07KF83F8003F01IFEI03F801FE03F800IF003F8003F8,R01F8L03EP07KF83F8003F01JFC003F801FC07F803IFC03F8003F8,R03EN0F8O07KF83F8003F01JFE003F803F807F807IFC03F8003F8,R0FFN03EO07KF83F8003F01KF003F807F007F80JFC03F8003F8,Q01FFCN0FO07KF83F8003F01KF803F80FF007F80JFC03F8003F8,Q0787FN078N07KF03F8003F01KFC03F80FE007F81FF07C03F8003F8,Q0F03FEM01CP07F8003F8003F01FC07FC03F81FC007F81FC00403F8003F8,P01E01FF8M0EP07F8003F8003F01FC03FC03F83F8007F81FCJ03F8003F8,P03C01FFEM07P07F8003F8003F01FC01FC03F87F8007F81FCJ03F8003F8,P07800IFM038O07F8003F8003F01FC01FC03F87FI07F81FCJ03F8003F8,P07I07FFCL01CO07F8003F8003F01FC01FC03F8FEI07F81FEJ03F8003F8,P0EI03FFEM0EO07F8003F8003F01FC01FC03F9FCI07F81FFJ03F8003F8,O01CI03IF8L07O07F8003F8003F01FC03FC03FBF8I07F81FFEI03F8007F8,O038I01IFCL07O07F8003F8003F01FC03F803IF8I07F80IF8003LF8,O078I01IFEL038N07F8003F8003F01KF803IFJ07F807FFE003LF8,O07J01JFL01CN07F8003F8003F01KF003IF8I07F803IF803LF8,O0EK0JF8K01CN07F8003F8003F01JFE003IFCI07F801IFC03LF8,O0EK0JFCL0EN07F8003F8003F01JFC003IFEI07F8007FFC03LF8,N01CK0JFCL06N07F8003F8003F01JF8003F9FEI07F8001FFE03F8003F8,N01CK07IFEL07N07F8003F8003F01FC3FC003F9FFI07F8I03FE03F8003F8,N038K07JFL03N07F8003F8003F01FC1FE003F8FF8007F8I01FE03F8003F8,N038K07JFL038M07F8003FC007F01FC1FE003F87FC007F8J0FE03F8003F8,N03L07JF8K038M07F8003FC007F01FC0FF003F83FC007F8J0FF03F8003F8,N07L03JF8K018M07F8001FC007F01FC0FF803F81FE007F8J0FE03F8003F8,N07L03JFCK01CM07F8001FE00FF01FC07F803F81FF007F8J0FE03F8003F8,N06L03JFCK01CM07F8001FF01FE01FC03FC03F80FF807F81C01FE03F8003F8,N0EL03JFCL0CM07F8I0KFE01FC03FC03F807FC07F81JFE03F8003F8,N0EL03JFCL0CM07F8I0KFC01FC01FE03F803FC07F81JFC03F8003F8,N0CL03JFCL0EM07F8I07JFC01FC01FF03F803FE07F81JF803F8003F8,N0CL03JFCL0EM07F8I03JF001FC00FF03F801FF07F81JF003F8003F8,N0CL03JFCL06M07F8J0IFE001FC003F83F800FF87F80IFE003F8003F8,N0CL03JFCL06T01FEgJ0FF,M01CL03JFCL06,M01CL07JFCL06,M01CL07JF8L06,:M01CL07JFM06,:M01CL0JFEM06,M01CL0JFCM06,N0CL0JF8I0FFC06gH0F8gL01E,N0CK01PF006K07KF81JFCI0IF807FI07F807FI03F00FEI01IF,N0CK01OFI0EK07KF81JFE003IFC07FI07F80FF8003F00FEI07IF8,N0CK03NF8I0EK07KF81JFE007IFC07FI07F80FFC003F00FE001JF8,N0EK03MF8J0CK07KF81JFC00JFC07FI07F80FFE003F00FE003JF8,N0EK07LFCK0CK07KF81JFC01JFC07FI07F80FFE003F00FE007JF8,N06K0LFCK01CK07JFE01JF803FF83C07FI07F80IF003F00FE007FE078,N06J01KFEL01CM07F8001FCJ07FC00407FI07F80IF803F00FE00FF8,N07J03JFEM018M07F8001FCJ07F8J07FI07F80IF803F00FE01FF,N07J07JFN038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FE,N03J0JFO038M07F8001FCJ0FFK07FI07F80IFC03F00FE01FC,N038003IF8O03N07F8001FCJ0FEK07FI07F80IFE03F00FE03FC,N018007FF8P07N07F8001FCI01FEK07FI07F80FEFF03F00FE03FC,N01C01FFCQ06N07F8001FCI01FEK07F8007F80FEFF03F00FE03F8,O0C07FCR0EN07F8001JF81FEK07LF80FE7F83F00FE03F8,O0E3FES0CN07F8001JF81FEK07LF80FE3FC3F00FE03F8,O07FFS01CN07F8001JF81FCK07LF80FE3FC3F00FE03F8,O07FT038N07F8001JF81FCK07LF80FE1FE3F00FE03F8,O038T038N07F8001JF01FEK07LF80FE0FE3F00FE03F8,O01CT07O07F8001FCI01FEK07F8007F80FE0FF3F00FE03F8,O01ET0EO07F8001FCI01FEK07FI07F80FE07F3F00FE03FC,P0ES01CO07F8001FCI01FEK07FI07F80FE07FBF00FE03FC,P07S03CO07F8001FCJ0FEK07FI07F80FE03IF00FE03FC,P038R078O07F8001FCJ0FFK07FI07F80FE01IF00FE01FE,P01CR0FP07F8001FCJ0FF8J07FI07F80FE01IF00FE01FE,Q0FQ01EP07F8001FCJ07FCJ07FI07F80FE00IF00FE01FF8,Q078P03CP07F8001JFC07FF01C07FI07F80FE007FF00FE00FFE038,Q03CP0FQ07F8001JFE03JFC07FI07F80FE007FF00FE007JF8,R0FO01EQ07F8001JFE01JFC07FI07F80FE003FF00FE003JF8,R07CN07CQ07F8001JFE00JFC07FI07F80FE003FF00FE001JF8,R03FM01FR07F8001JFE007IFC07FI07F80FE001FF00FEI0JF8,S0FCL0FCR07F8001JFE001IFC07FI07F80FEI0FF00FEI03IF8,S03F8J03F8gL03FFgL07FC,T0FFC007FC,T01KFE,U01IFE,,::::::::::::::::::::::::::::::::::^FS
^FO430,40
^BXN,4,200,,,,,2
^FDMFR '. $list['details']['mfr'] .'/SEQ '. $list['details']['seq'] .'/PNO '. $list['details']['pno'] .'^FS

^FO620,40^CFA,30^FDMFR '. $list['details']['mfr'] .' - PNO '. $list['details']['pno'] .'^FS
^FO620,75^CFA,30^FDSEQ '. $list['details']['seq'] .'^FS
^FO620,110^CFA,30^FDDMF '. $list['details']['dmf'] .' - EXP '. $list['details']['exp'] .'^FS';
                    }
                }

                $imp .= '^XZ';

            }

// Orjinal rfid yazmak için
            /*$imp .= '^XA
                            ^RFW,H,2,,A^FD'. $list['epc'] .'^FS
                            ^RFW,H,0,'. (strlen($list['user_data'])/2) .',3^FD'. $list['user_data'] .'^FS
                            ^XZ';*/

//^RFW,H,0,68,3^FD1E0064210040002A3464A0C72CF4D6A4C54A00420F1CB3A90392805DB3D32C2DC30A84346832C31C31C31C2A30C160C6A158420CB0CB0C70C70A9038F834D33D33D40000^FS

            $address = '192.168.1.100';

            if ($address == '0.0.0.0'){
                $address = $_SERVER['REMOTE_ADDR'];
            }

            $port = '9100';

            try {
                $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                $sockconnect = socket_connect($sock, $address, $port);
                socket_write($sock, $imp, strlen($imp));
                socket_close($sock);

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function ajaxItemCreate(Request $request)
    {
        if ($request->construct_type == 'construct_1') {
            $construct1Data = array(
                'mfr' => $request->mfr,
                'ser' => $request->ser,
                'pno' => $request->pno,
                'seq' => $request->seq,
                'pnr' => $request->pnr,
                'dmf' => str_replace('-','',$request->dmf),
                'exp' => str_replace('-','',$request->exp),
                'location' => $request->location,
                'loa' => $request->loa,
            );

            $const1Json = $this->construct1EpcCreate($construct1Data, $request->construct_type);
//$this->epcSatoPrinter($const1Json['epc'], $const1Json['user_data']);
            $dbAdded = $this->dbAdded(array('epc' => $const1Json['epc'], 'user_data' => $const1Json['user_data'], 'construct_type' => $request->construct_type, 'set_name' => $request->set_name, 'encoded' => 0, 'location' => $request->location, 'loa' => $request->loa, 'detail' => $construct1Data));
            $const1Json['dbAdded'] = $dbAdded;
            return response()->json($const1Json, 200);

        }else if ($request->construct_type == 'construct_2') {
            $construct2Data = array(
                'mfr' => $request->mfr,
                'ser' => $request->ser,
                'pno' => $request->pno,
                'seq' => $request->seq,
                'pnr' => $request->pnr,
                'dmf' => str_replace('-','',$request->dmf),
                'exp' => str_replace('-','',$request->exp),
                'location' => $request->location,
                'loa' => $request->loa,
            );

            $const2Json = $this->construct2EpcCreate($construct2Data, $request->construct_type);
//$this->epcSatoPrinter($const2Json['epc'], $const2Json['user_data']);
            $dbAdded = $this->dbAdded(array('epc' => $const2Json['epc'], 'user_data' => $const2Json['user_data'], 'construct_type' => $request->construct_type, 'set_name' => $request->set_name, 'encoded' => 0, 'location' => $request->location, 'loa' => $request->loa, 'detail' => $construct2Data));
            $const2Json['dbAdded'] = $dbAdded;
            return response()->json($const2Json, 200);
        }
    }

    public function epcCreate(Request $request)
    {
        $json = array();

        $mfr = $request->mfr;
        $pno = $request->pno;
        $pnr = $request->pnr;
        $ser = $request->ser;
        $dmf = $request->dmf;
        $seq = $request->seq;

        $epcBin = '00111011'; // EPC header
        $epcBin .= '001111'; // EPC filter


        if (strlen($mfr) == 5) {
            $epcBin .= decbin(0x20);

            $mfrCharArray = str_split($mfr);

            foreach ($mfrCharArray as $char) {
                $epcBin .= $this->charTo6BitBinary($char);
            }

        }else {
            dd('epcCreate => invalid mfr');
        }

        /*if (strlen($pno) > 0 || strlen($pno) <= 32) {

                    $pnoCharArray = str_split($pno);

                    foreach ($pnoCharArray as $char) {
                        $epcBin .= $this->charTo6BitBinary($char);
                        //$epcBin .= str_pad(decbin($this->charTo6BitBinary($char)), 6,'0', STR_PAD_LEFT);
                    }

                }else {
                    dd('epcCreate => invalid pno');
                }*/

        $epcBin .= '000000';

        if (strlen($ser) > 0 || strlen($ser) <= 30) {

            $serCharArray = str_split($ser);

            foreach ($serCharArray as $char) {
                $epcBin .= $this->charTo6BitBinary($char);
//$epcBin .= str_pad(decbin($this->charTo6BitBinary($char)), 6,'0', STR_PAD_LEFT);
            }

        }else {
            dd('epcCreate => invalid serial');
        }

        $epcBin .= '000000';

//dd($epcBin);

        $mod8 = strlen($epcBin)%8;
        $shiftValue = 0;

        if ($mod8 > 0) {
            $shiftValue = 8-$mod8;
        }

        $padValue = '';
        for ($i=0;$i<$shiftValue;$i++) {
            $padValue .= '0';
        }

//dd($epcBin . $padValue);
        $epcBin .= $padValue;

        $epc = '';
        for ($i=0;$i<strlen($epcBin);$i=$i+16) {
            $epc .= str_pad($this->binHex(substr($epcBin,$i,16)), 4, '0', STR_PAD_LEFT);
//dd($epc);
        }


        $json = array(
            'epc' => strtoupper($epc)
        );

        echo json_encode($json);
    }





    public function dbAdded($data = array())
    {
        try {
            $setControl = Sets::where('set_name', $data['set_name'])->get();
            if ($setControl->count() > 0) {
                $setInfo = $setControl->first();
            }else {
                $newSet = new Sets();
                $newSet->set_name = $data['set_name'];
                $newSet->save();

                $setInfo = $newSet;
            }

            $itemControl = Items::where('epc', $data['epc'])->get();
            if ($itemControl->count() <= 0) {
//İtems Crate
                $newItem = new Items();
                $newItem->set_id = $setInfo->id;
                $newItem->mfr = $data['detail']['mfr'];
                $newItem->ser = $data['detail']['ser'];
                $newItem->pno = $data['detail']['pno'];
                $newItem->seq = $data['detail']['seq'];
                $newItem->pnr = $data['detail']['pnr'];
                $newItem->dmf = $data['detail']['dmf'];
                $newItem->exp = $data['detail']['exp'];
                $newItem->epc = $data['epc'];
                $newItem->construct_type = $data['construct_type'];
                $newItem->save();

// EPC create
                $newEpcTag = new EpcTags();
                $newEpcTag->item_id = $newItem->id;
                $newEpcTag->epc = $data['epc'];
                $newEpcTag->user_data = $data['user_data'];
                $newEpcTag->encoded = $data['encoded'];
                $newEpcTag->location = $data['location'];
                $newEpcTag->loa = $data['loa'];
                $newEpcTag->save();

                $data['detail']['construct_type'] = $data['construct_type'];
                $data['detail']['epc_tag_id'] = $newEpcTag->id;
                return $data['detail'];
            }else {
                return false;
            }
        }catch (\Exception $e) {
            return false;
        }
    }
}
