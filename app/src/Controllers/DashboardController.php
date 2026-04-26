<?php
declare(strict_types=1);

class DashboardController
{
    public function __construct(
        private PortModel $portModel,
        private DeviceModel $deviceModel
    ) {
        Auth::requireLogin();
    }

    public function index(): void
    {
        $siteId      = (int) Session::get('current_site_id');
        $ports       = $this->portModel->all($siteId);
        $portStats   = $this->portModel->stats($siteId);
        $devices     = $this->deviceModel->all($siteId);
        $deviceCount = count($devices);
        $ipCount     = $this->deviceModel->ipCount($siteId);

        render('dashboard', [
            'navActive'   => 'dashboard',
            'ports'       => $ports,
            'portStats'   => $portStats,
            'devices'     => $devices,
            'deviceCount' => $deviceCount,
            'ipCount'     => $ipCount,
        ]);
    }
}
