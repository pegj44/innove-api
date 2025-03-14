<?php

namespace App\Http\Controllers;

use App\Models\TradePair;
use App\Models\TradeQueueModel;
use App\Models\TradeReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TradeReportController extends Controller
{
    public function getLatestTrades()
    {
        $trades = TradeQueueModel::where('account_id', auth()->user()->account_id)
            ->where('status', 'closed')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        if (empty($trades)) {
            return response()->json([]);
        }

        foreach ($trades as $trade) {
            $trade->data = maybe_unserialize($trade->data);
        }

        return response()->json($trades);
    }

    public function reportCloseTrade(Request $request)
    {
        $dataRaw = $request->get('data');
        $dataArr = explode('|', $dataRaw);
        $dataFormatted = [];

        foreach ($dataArr as $item) {
            $subData = explode(':', $item);
            $dataFormatted[$subData[0]] = $subData[1];
        }

        return response()->json(['apiData' => $dataFormatted]);
    }

    public function getOngoingTrades($unitId)
    {
        $items = TradeReport::with(['tradingAccountCredential.funder', 'tradingAccountCredential.userAccount.tradingUnit'])
            ->where('account_id', auth()->user()->account_id)
            ->where('status', 'trading')
            ->whereHas('tradingAccountCredential.userAccount.tradingUnit', function($query) use ($unitId) {
                $query->where('unit_id', $unitId);
            })
            ->get()
            ->map(function ($item) {
                // Assuming the funder_account_id is present in tradingAccountCredential relation
                $funderAccountId = $item->tradingAccountCredential->funder_account_id;
//
//                // Add formatted funder_account_id to each item
//                $item->tradingAccountCredential->funder_account_id = [
//                    'long' => $funderAccountId,
//                    'short' => getFunderAccountShortName($funderAccountId)
//                ];

                $item->tradingAccountCredential->funder_account_id_short = getFunderAccountShortName($funderAccountId);

                return $item;
            });

        if ($items->isEmpty()) {
            return response()->json([]);
        }

        return response()->json($items);
    }

    public function getReports(Request $request)
    {
        $data = $request->all();
        $items = TradeReport::with(
            'tradingAccountCredential.userAccount.tradingUnit',
            'tradingAccountCredential.package',
            'tradingAccountCredential.package.funder',
            'tradingAccountCredential.funder.metadata',
            'tradingAccountCredential.userAccount.funderAccountCredential',
            'tradingAccountCredential.historyV3',
            'tradingAccountCredential.payouts'
        )
            ->where('account_id', auth()->user()->account_id)
            ->whereHas('tradingAccountCredential', function($query) {
                $query->where('status', 'active');
            });

        if ($request->get('current_phase')) {
            $items->whereHas('tradingAccountCredential', function($query) use ($data) {
                $query->where('current_phase', $data['current_phase']);
            });
        }

        if ($request->get('tradingAccountIds')) {
            $items->whereIn('trade_account_credential_id', $data['tradingAccountIds']);
        }

        if ($request->get('ids')) {
            $items->whereIn('id', $data['ids']);
        }

        if ($request->get('raw')) {
            return $items->get();
        }

        return response()->json($items->get());
    }

    public function updateLatestEquity(Request $request)
    {
        $validator = Validator::make($request->only(['account_id', 'latest_equity']), [
            'account_id' => ['required'],
            'latest_equity' => ['required', 'regex:/^\d+(\.\d{1,2})?$/']
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        try {
            TradeReport::where('user_id', auth()->id())
                ->whereHas('tradingAccountCredential', function($query) use ($request) {
                    $query->where('account_id', $request->get('account_id'));
                })
                ->update(['latest_equity' => $request->get('latest_equity')]);

            $machineJob = TradePairAccountsController::updateEquityUpdateStatus(auth()->id(), $request->get('account_id'), 'complete');
            $pairedItems = TradePairAccountsController::pairItems();

            if (!empty($pairedItems)) {
                return response()->json($pairedItems);
            }

            return response()->json(['message' => __('Equity updated')]);
        } catch (\Exception $e) {
            return response()->json(['error_updateLatestEquity' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->except('_token');
            $validator = $this->validateUserInput($data);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data['account_id'] = auth()->user()->account_id;
            $credential = TradeReport::create($data);

            if (!$credential) {
                return response()->json(['errors' => __('Failed to create trade report.')]);
            }

            return response()->json(['message' => __('Successfully created trade report.')]);
        } catch (\Exception $e) {
            info(print_r([
                'errorCreateTradeReport' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error creating trade report.')]);
        }
    }

    /**
     * @todo add support for update data without needing the required fields.
     * @param $data
     * @return \Illuminate\Validation\Validator
     */
    private function validateUserInput($data, $action = 'store')
    {
        $inputsToValidate = [
            'trade_account_credential_id' => ['required', 'numeric'],
            'starting_daily_equity' => ['required', 'numeric'],
            'latest_equity' => ['required', 'numeric'],
            'status' => ['regex:/^[a-zA-Z0-9- ]+$/'],
        ];

        if ($action === 'update') {
            unset($inputsToValidate['trade_account_credential_id']);
        }

        return Validator::make($data, $inputsToValidate);
    }

    public function edit(string $id)
    {
        try {
            $item = TradeReport::with('tradingAccountCredential.userAccount.tradingUnit', 'tradingAccountCredential.funder.metadata')
                ->where('id', $id)
                ->where('account_id', auth()->user()->account_id)->first();

            return response()->json($item);
        } catch (\Exception $e) {
            info(print_r([
                'errorEditCredential' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error retrieving data.')]);
        }
    }

    public function updateByFunderAccount(Request $request)
    {
        $data = $request->except('_token');

        $inputsToValidate = [
            'account_url_type' => ['required'],
            'account_url' => ['required', 'url'],
            'account_id' => ['required']
        ];

        $validator = Validator::make($data, $inputsToValidate);

        if ($validator->fails()) {
            return response()->json(['validation_errors' => $validator->errors()], 422);
        }

        $funder = FunderController::getByUrl($request->get('account_url_type'), $request->get('account_url'));

        if (!$funder) {
            return response()->json(['errors' => __('Funder not found.')]);
        }

        $funder_id = $funder->id;
        $account_id = $request->get('account_id');

        $tradeReport = TradeReport::whereHas('tradingAccountCredential', function (Builder $query) use ($account_id, $funder_id) {
            $query->where('account_id', $account_id)
                ->whereHas('funder', function (Builder $query) use ($funder_id) {
                    $query->where('id', $funder_id);
                });
        })->first();

        if (!$tradeReport) {
            return [];
        }

        try {
            $tradeReport->fill($data);
            $update = $tradeReport->update();

            if (!$update) {
                return response()->json(['errors' => __('Failed to update trade report.')]);
            }

            return response()->json(['message' => __('Successfully updated trade account.')]);
        } catch (\Exception $e) {
            info(print_r([
                'errorPatchTradeReport' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error updating trade report.')]);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $data = $request->except('_token');
            $validator = $this->validateUserInput($data, 'update');

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $item = TradeReport::where('id', $id)->where('account_id', auth()->user()->account_id)->first();

            if (!$item) {
                return response()->json(['errors' => __('Unable to find trade report.')]);
            }

            if ($data['starting_daily_equity'] != $data['latest_equity']) {
//                TradeController::recordTradeHistory($item, $data['latest_equity']);
            }

            $item->fill($data);
            $update = $item->update();

            if (!$update) {
                return response()->json(['errors' => __('Failed to update trade report.')]);
            }

            return response()->json(['message' => __('Successfully updated trade account.')]);
        } catch (\Exception $e) {
            info(print_r([
                'errorUpdateTradeReport' => $e->getMessage()
            ], true));
            return response()->json(['errors' => __('Error updating trade report.')]);
        }
    }

    public function destroy(string $id)
    {
        try {
            $item = TradeReport::where('id', $id)->where('user_id', auth()->id())->first();

            if (!$item) {
                return response()->json(['errors' => 'Failed to remove trade report.']);
            }

            $item->delete();

            return response()->json([
                'message' => __('Successfully removed trade report.')
            ]);
        } catch (\Exception $e) {
            info(print_r([
                'errorRemoveTradeReport' => $e->getMessage()
            ], true));

            return response()->json(['errors' => 'Error deleting the trade report.']);
        }
    }
}
