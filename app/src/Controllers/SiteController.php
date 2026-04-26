<?php
declare(strict_types=1);

class SiteController
{
    public function __construct(private SiteModel $siteModel)
    {
        Auth::requireLogin();
    }

    public function index(): void
    {
        $sites = $this->siteModel->all();
        render('sites', [
            'navActive'     => 'sites',
            'title'         => 'Sites',
            'sites'         => $sites,
            'currentSiteId' => (int) Session::get('current_site_id'),
        ]);
    }

    public function create(): void
    {
        render('site_form', [
            'navActive' => 'sites',
            'title'     => 'New Site',
            'site'      => null,
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $data = $this->validateSiteData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header('Location: /sites/new');
            exit;
        }

        if ($this->siteModel->slugExists($data['slug'])) {
            Session::flash('error', 'A site with that slug already exists.');
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header('Location: /sites/new');
            exit;
        }

        try {
            $this->siteModel->create($data);
            Session::flash('success', 'Site created.');
        } catch (PDOException) {
            Session::flash('error', 'A database error occurred. Please try again.');
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header('Location: /sites/new');
            exit;
        }

        header('Location: /sites');
        exit;
    }

    public function edit(int $id): void
    {
        $site = $this->siteModel->find($id);
        if (!$site) {
            http_response_code(404);
            exit('Site not found.');
        }
        render('site_form', [
            'navActive' => 'sites',
            'title'     => 'Edit Site',
            'site'      => $site,
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();

        $site = $this->siteModel->find($id);
        if (!$site) {
            http_response_code(404);
            exit('Site not found.');
        }

        $data = $this->validateSiteData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header("Location: /sites/{$id}/edit");
            exit;
        }

        if ($this->siteModel->slugExists($data['slug'], $id)) {
            Session::flash('error', 'A site with that slug already exists.');
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header("Location: /sites/{$id}/edit");
            exit;
        }

        try {
            $this->siteModel->update($id, $data);
            if ((int) Session::get('current_site_id') === $id) {
                Session::set('current_site_name', $data['name']);
            }
            Session::flash('success', 'Site updated.');
        } catch (PDOException) {
            Session::flash('error', 'A database error occurred. Please try again.');
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header("Location: /sites/{$id}/edit");
            exit;
        }

        header('Location: /sites');
        exit;
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();

        if (!$this->siteModel->find($id)) {
            http_response_code(404);
            exit('Site not found.');
        }

        try {
            $this->siteModel->delete($id);
            if ((int) Session::get('current_site_id') === $id) {
                Session::forget('current_site_id');
                Session::forget('current_site_name');
            }
            Session::flash('success', 'Site deleted.');
        } catch (PDOException $e) {
            $msg = str_contains(strtolower($e->getMessage()), 'foreign key')
                   || str_contains(strtolower($e->getMessage()), 'violates')
                ? 'Cannot delete a site that has devices. Remove or reassign all devices first.'
                : 'A database error occurred.';
            Session::flash('error', $msg);
        }

        header('Location: /sites');
        exit;
    }

    public function switchTo(int $id): void
    {
        $this->verifyCsrf();

        $site = $this->siteModel->find($id);
        if (!$site) {
            Session::flash('error', 'Site not found.');
            header('Location: /sites');
            exit;
        }

        Session::set('current_site_id', $id);
        Session::set('current_site_name', $site['name']);

        $redirect = $_POST['redirect'] ?? '/';
        // Allow only same-origin paths: must start with / followed by a non-/ non-\ character, or be exactly /.
        $redirect = (preg_match('#^/(?:[^/\\\\]|$)#', $redirect)) ? $redirect : '/';
        header("Location: {$redirect}");
        exit;
    }

    private function validateSiteData(array $post): array|string
    {
        $name = trim($post['name'] ?? '');
        if ($name === '') {
            return 'Site name is required.';
        }
        if (strlen($name) > 128) {
            return 'Site name must be 128 characters or fewer.';
        }

        $slug = trim(strtolower($post['slug'] ?? ''));
        if ($slug === '') {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
            $slug = trim($slug, '-');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            return 'Slug must contain only lowercase letters, numbers, and hyphens.';
        }
        if (strlen($slug) > 64) {
            return 'Slug must be 64 characters or fewer.';
        }

        return [
            'name'        => $name,
            'slug'        => $slug,
            'description' => substr(trim($post['description'] ?? ''), 0, 1000),
        ];
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }
    }
}
