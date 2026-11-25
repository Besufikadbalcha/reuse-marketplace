@extends('layouts.marketplace')

@section('title', __('New listing').' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('seller.products.index') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to my listings') }}
        </x-button>
    </div>

    <x-page-heading
        :eyebrow="__('Seller workspace')"
        :title="__('New listing')"
        :description="__('Share the details and photos buyers need to decide.')"
    />

    @include('seller.products.partials.form')
@endsection
