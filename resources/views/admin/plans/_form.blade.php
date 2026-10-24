<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Plan Name</label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $plan->name ?? '') }}" placeholder="e.g. Day Pass" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Type</label>
        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
            @foreach(['daily','weekly','monthly'] as $type)
            <option value="{{ $type }}" {{ old('type', $plan->type ?? '') === $type ? 'selected' : '' }}>
                {{ ucfirst($type) }}
            </option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Duration (hours)</label>
        <input type="number" name="duration_hours" class="form-control @error('duration_hours') is-invalid @enderror"
               value="{{ old('duration_hours', $plan->duration_hours ?? '') }}" min="1" required>
        @error('duration_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Price (KES)</label>
        <input type="number" name="price" step="0.01" class="form-control @error('price') is-invalid @enderror"
               value="{{ old('price', $plan->price ?? '') }}" min="0" required>
        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Status</label>
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                   {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Upload Limit</label>
        <input type="text" name="bandwidth_up" class="form-control @error('bandwidth_up') is-invalid @enderror"
               value="{{ old('bandwidth_up', $plan->bandwidth_up ?? '1M') }}" placeholder="e.g. 2M" required>
        <div class="form-text">MikroTik format: 512k, 1M, 5M</div>
        @error('bandwidth_up')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Download Limit</label>
        <input type="text" name="bandwidth_down" class="form-control @error('bandwidth_down') is-invalid @enderror"
               value="{{ old('bandwidth_down', $plan->bandwidth_down ?? '1M') }}" placeholder="e.g. 2M" required>
        @error('bandwidth_down')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Session Timeout (seconds)</label>
        <input type="number" name="session_timeout" class="form-control"
               value="{{ old('session_timeout', $plan->session_timeout ?? '') }}" placeholder="0 = unlimited">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Idle Timeout (seconds)</label>
        <input type="number" name="idle_timeout" class="form-control"
               value="{{ old('idle_timeout', $plan->idle_timeout ?? '') }}" placeholder="e.g. 300">
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Data Limit (optional)</label>
        <input type="text" name="data_limit" class="form-control @error('data_limit') is-invalid @enderror"
               value="{{ old('data_limit', $plan->data_limit ?? '') }}" placeholder="e.g. 5G">
        <div class="form-text">RouterOS size format. Leave blank for unlimited.</div>
        @error('data_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2"
                  placeholder="Optional plan description">{{ old('description', $plan->description ?? '') }}</textarea>
    </div>
</div>
