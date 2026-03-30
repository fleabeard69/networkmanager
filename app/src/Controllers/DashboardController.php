<?php
declare(strict_types=1);

class DashboardController
{
    public function __construct(
        private PortModel $portModel,
        private DeviceModel $deviceModel
    ) {}

    public function index(): void
    {
        $ports       = $this->portModel->all();
        $portStats   = $this->portModel->stats();
        $devices     = $this->deviceModel->all();
        $deviceCount = count($devices);
        $ipCount     = $this->deviceModel->ipCount();

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
