---
paths:
  - 'app/Services/**'
---

# Services

## Akses tabel workflow langsung
Access workflow and communication tables with DB::table(); do not introduce table-specific Eloquent models or a repository/query-object layer.

## Gunakan service statis
Keep services stateless with public static methods, and call shared service operations statically.
