<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrailerRequest;
use App\Services\CenterManagement\DriverService;
use App\Services\CenterManagement\ReportService;
use App\Services\CenterManagement\ShipmentService;
use App\Services\CenterManagement\TrailerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Shipment;
use App\Http\Resources\ShipmentResource;

class CenterManagementController extends Controller
{
    protected $trailerService;
    protected $driverService;
    protected $reportService;
    protected $shipmentService;

    public function __construct
    (
        TrailerService  $trailerService,
        DriverService   $driverService,
        ReportService   $reportService,
        ShipmentService $shipmentService,
    )
    {
        $this->trailerService = $trailerService;
        $this->driverService = $driverService;
        $this->reportService = $reportService;
        $this->shipmentService = $shipmentService;
    }

    public function getAvailableTrailersByCenter()
    {
        $result = $this->trailerService->getAvailableTrailersByCenter();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'count' => count($result['data'])
        ]);
    }

     public function getIncomingTrailers()
     {
         $result = $this->trailerService->getIncomingTrailers();
         //return response()->json($result['centerId']);
         if (!$result['success']) {
             return response()->json([
                 'success' => false,
                 'message' => $result['message'],
                 'error' => $result['error'] ?? null
             ], 500);
         }

         return response()->json([
             'success' => true,
             'data' => $result['data'],
             'count' => count($result['data']),

         ]);
     }

    public function checkCapacity($trailerId, $shipmentId)
    {
        $result = $this->trailerService->checkCapacity($trailerId, $shipmentId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }

    public function assignToTrailer($trailerId, $shipmentId)
    {
        $result = $this->trailerService->assignToTrailer($trailerId, $shipmentId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    public function transferTrailer($trailerId)
    {
        $result = $this->trailerService->transferTrailer($trailerId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }


    public function arrivedTrailer($trailerId)
    {
        // $validator = Validator::make($request->all(), [
        //     'destination_center_id' => 'required|exists:centers,id'
        // ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'message' => 'Invalid Data',
        //         'errors' => $validator->errors()
        //     ], 400);
        // }

        $result = $this->trailerService->arrivedTrailer($trailerId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    public function removeFromTrailer(Request $request, $trailerId, $shipmentId)
    {
        $result = $this->trailerService->removeFromTrailer($request, $trailerId, $shipmentId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    public function getTrailerShipments($trailerId)
    {
        $result = $this->trailerService->getTrailerShipments($trailerId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }

    public function getFinancialReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'center_id' => 'nullable|exists:centers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->reportService->getFinancialReport($request->all());

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }

    public function getDashboardStats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|in:day,week,month,year'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->reportService->getDashboardStats($request->period ?? 'month');

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }

    public function getShipmentsReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'center_id' => 'nullable|exists:centers,id',
            'status' => 'nullable|string|in:pending,offered_pickup_driver,picked_up,in_transit_between_centers,arrived_at_destination_center,offered_delivery_driver,out_for_delivery,delivered,cancelled',
            'shipment_type' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->reportService->getShipmentsReport($request->all());

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }

    public function createDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:6',
            'center_id' => 'nullable|exists:centers,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_approved' => 'nullable|boolean',
            'active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->driverService->createDriver($request->all());

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 201);
    }

    public function updateDriver(Request $request, $driverId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $driverId,
            'phone' => 'sometimes|string|unique:users,phone,' . $driverId,
            'password' => 'sometimes|string|min:6',
            'center_id' => 'nullable|exists:centers,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_approved' => 'nullable|boolean',
            'active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->driverService->updateDriver($driverId, $request->all());

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    public function deleteDriver($driverId)
    {
        $result = $this->driverService->deleteDriver($driverId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message']
        ]);
    }

    public function blockDriver($driverId)
    {
        $result = $this->driverService->blockDriver($driverId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    public function unblockDriver($driverId)
    {
        $result = $this->driverService->unblockDriver($driverId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    public function approveDriver($driverId)
    {
        $result = $this->driverService->approveDriver($driverId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    public function getDriverDetails($driverId)
    {
        $result = $this->driverService->getDriverDetails($driverId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }

    public function getAllDrivers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'center_id' => 'nullable|exists:centers,id',
            'status' => 'nullable|in:active,blocked',
            'approved' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->driverService->getAllDrivers($request->all());

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }

    public function getShipmentDetails($shipmentId)
    {
        try {
            $shipment = Shipment::with([
                'client',
                'recipient',
                'centerFrom',
                'centerTo',
                //'pickupDriver',
                //'deliveryDriver',
                'trailer'
            ])->findOrFail($shipmentId);

            return response()->json([
                'success' => true,
                'data' => [
                    'shipment' => new ShipmentResource($shipment)
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'الشحنة غير موجودة',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل الشحنة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelShipment($shipmentId)
    {
        try {
            // نستخدم التابع cancel من ShipmentService
            $shipment = $this->shipmentService->cancel($shipmentId);

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الشحنة بنجاح',
                'data' => [
                    'shipment' => new ShipmentResource($shipment)
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'الشحنة غير موجودة',
                'error' => $e->getMessage()
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء الشحنة',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الشحنة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCenterShipments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,offered_pickup_driver,picked_up,in_transit_between_centers,arrived_at_destination_center,offered_delivery_driver,out_for_delivery,delivered,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'shipment_type' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $centerManagerId = Auth::id();
        $result = $this->shipmentService->getShipmentsForCenterManager($centerManagerId, $request->all());

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }

    public function getCenterShipmentStats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|in:day,week,month,year'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Data',
                'errors' => $validator->errors()
            ], 400);
        }

        $centerManagerId = Auth::id();
        $result = $this->shipmentService->getCenterShipmentStats($centerManagerId, $request->period ?? 'month');

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ], $result['status'] ?? 500);
        }

        return response()->json($result['data']);
    }
    public function store(StoreTrailerRequest $request)
    {
        $trailer = $this->trailerService->createTrailer($request->validated());

        return response()->json([
            'message' => 'Trailer created successfully.',
            'trailer' => $trailer
        ]);
    }
}
