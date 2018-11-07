<?php

namespace App\Http\Controllers;

use App\Models\ManualReview;
use App\Models\ManualReviewGoogle;
use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;

class ManualReviewController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = request()->validate([
            'year'  => 'required|integer',
            'person_id' => 'sometimes|integer'
        ]);

        $this->authorize('view', ManualReview::class);
        return $this->success(ManualReview::findForQuery($query));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('store', ManualReview::class);

        $manualReview = new \App\Models\ManualReview;
        $this->fromRest($manualReview);

        if ($manualReview->save()) {
            return $this->success($manualReview);
        }

        return $this->restError($manualReview);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ManualReview  $manualReview
     * @return \Illuminate\Http\Response
     */
    public function show(ManualReview $manualReview)
    {
        $this->authorize('view', ManualReview::class);
        return $this->success($manualReview);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ManualReview  $manualReview
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ManualReview $manualReview)
    {
        $this->authorize('update', ManualReview::class);
        $this->fromRest($manualReview);

        if ($manualReview->save()) {
            return $this->success($manualReview);
        }

        return $this->restError($manualReview);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ManualReview  $manualReview
     * @return \Illuminate\Http\Response
     */
    public function destroy(ManualReview $manualReview)
    {
        $this->authorize('delete', ManualReview::class);
        $manualReview->delete();
        $this->log('manual-review-delete', 'ManualReview Deleted', [ 'id' => $manualReview->id]);
        return $this->restDeleteSuccess();
    }
}
