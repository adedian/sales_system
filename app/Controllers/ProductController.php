<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\MasterData;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', ''),
            'page' => (int) $request->input('page', 1),
        ];

        $result = Product::search($filters);

        $this->view('products/index', [
            'pageTitle' => 'Katalog Produk',
            'products' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('products/form', [
            'pageTitle' => 'Tambah Produk',
            'breadcrumb' => ['Katalog Produk' => url('/products'), 'Tambah'],
            'product' => null,
            'categories' => MasterData::allAsMap('product_categories', true),
            'units' => MasterData::allAsMap('units', true),
        ]);
    }

    public function store(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/products/create');

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:200',
            'default_price' => 'numeric',
        ]);

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            Session::flashOld($request->only(['name', 'category_id', 'unit_id', 'default_price', 'description']));
            $this->redirect('/products/create');

            return;
        }

        $actor = Auth::user();

        $product = Product::createWithCode([
            'name' => trim((string) $request->input('name')),
            'category_id' => $this->nullableInt($request->input('category_id')),
            'unit_id' => $this->nullableInt($request->input('unit_id')),
            'default_price' => $request->input('default_price') !== '' ? (float) $request->input('default_price') : null,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'is_active' => 1,
            'created_by' => $actor['id'],
            'updated_by' => $actor['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log((int) $actor['id'], 'product_created', 'product', $product['id'], null, ['product_code' => $product['product_code'], 'name' => $request->input('name')]);

        Session::flash('success', "Produk {$product['product_code']} berhasil dibuat.");
        $this->redirect('/products');
    }

    public function edit(Request $request, array $params): void
    {
        $product = Product::find((int) $params['id']);
        if ($product === null) {
            $this->abort(404);

            return;
        }

        $this->view('products/form', [
            'pageTitle' => 'Ubah Produk',
            'breadcrumb' => ['Katalog Produk' => url('/products'), 'Ubah'],
            'product' => $product,
            'categories' => MasterData::allAsMap('product_categories', true),
            'units' => MasterData::allAsMap('units', true),
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $product = Product::find((int) $params['id']);
        if ($product === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/products/' . $product['id'] . '/edit');

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:200',
            'default_price' => 'numeric',
        ]);

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            $this->redirect('/products/' . $product['id'] . '/edit');

            return;
        }

        $actor = Auth::user();

        $newData = [
            'name' => trim((string) $request->input('name')),
            'category_id' => $this->nullableInt($request->input('category_id')),
            'unit_id' => $this->nullableInt($request->input('unit_id')),
            'default_price' => $request->input('default_price') !== '' ? (float) $request->input('default_price') : null,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'updated_by' => $actor['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Product::update((int) $product['id'], $newData);

        AuditLogger::log((int) $actor['id'], 'product_updated', 'product', (int) $product['id'], ['name' => $product['name']], ['name' => $newData['name']]);

        Session::flash('success', 'Produk berhasil diperbarui.');
        $this->redirect('/products');
    }

    public function toggleStatus(Request $request, array $params): void
    {
        $product = Product::find((int) $params['id']);
        if ($product === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/products');

            return;
        }

        $actor = Auth::user();
        $newStatus = (int) $product['is_active'] === 1 ? 0 : 1;
        Product::update((int) $product['id'], ['is_active' => $newStatus, 'updated_by' => $actor['id']]);

        AuditLogger::log((int) $actor['id'], $newStatus ? 'product_activated' : 'product_deactivated', 'product', (int) $product['id'], ['is_active' => (int) $product['is_active']], ['is_active' => $newStatus]);

        Session::flash('success', 'Status produk berhasil diubah.');
        $this->redirect('/products');
    }

    public function destroy(Request $request, array $params): void
    {
        $product = Product::find((int) $params['id']);
        if ($product === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/products');

            return;
        }

        Product::softDelete((int) $product['id']);

        AuditLogger::log((int) Auth::id(), 'product_deleted', 'product', (int) $product['id'], ['product_code' => $product['product_code']], null);

        Session::flash('success', "Produk {$product['product_code']} dihapus.");
        $this->redirect('/products');
    }

    private function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
