# Scalability Overview

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Scale horizontally with shared MySQL, Redis where selected, shared/object media, supervised workers, and coordinated scheduling. Keep controllers thin, avoid N+1/unbounded graphs, bound pagination and generation, use queue backpressure, and cache only reads with explicit invalidation. Transactional state remains database/service authoritative.
