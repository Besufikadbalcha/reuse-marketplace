@extends('layouts.marketplace')

@section('title', __('Edit listing').' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('seller.products.show', $product) }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to listing') }}
        </x-button>
    </div>

    <x-page-heading
        :eyebrow="__('Seller workspace')"
        :title="__('Edit listing')"
        :description="__('Changing a submitted listing returns it to pending review.')"
    />

    @include('seller.products.partials.form')
@endsection
