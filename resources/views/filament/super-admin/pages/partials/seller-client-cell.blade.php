@php
    $phone = $row['contact_phone'] ?? null;
@endphp
<a href="{{ $this->tenantEditUrl($row['tenant_id']) }}" class="seller-client-link">
    <strong>{{ $row['tenant_name'] }}</strong>
</a>
<br>
<span class="seller-client-meta">/{{ $row['tenant_id'] }}</span>
@if (filled($phone))
    <br>
    <a href="tel:{{ $phone }}" class="seller-phone-link">{{ $phone }}</a>
@endif
