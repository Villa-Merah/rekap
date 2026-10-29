---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Validasi request di controller
Validate request input in controller actions with $request->validate(); do not introduce Form Request classes.

## Akses tabel workflow langsung
Access workflow and communication tables with DB::table(); do not introduce table-specific Eloquent models or a repository/query-object layer.

## Tangani timestamp workflow sebagai UTC
Parse workflow timestamps with CarbonImmutable in config('workflow.timezone'), convert them to UTC for storage, and parse stored timestamps as UTC.
