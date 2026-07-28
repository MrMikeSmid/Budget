import { timingSafeEqual } from "node:crypto";
import type { Request, Response, NextFunction } from "express";
import { getBearerToken } from "./config.js";

function safeEqual(a: string, b: string): boolean {
  const bufA = Buffer.from(a);
  const bufB = Buffer.from(b);
  if (bufA.length !== bufB.length) {
    return false;
  }
  return timingSafeEqual(bufA, bufB);
}

export function bearerAuth(req: Request, res: Response, next: NextFunction): void {
  const header = req.header("authorization");
  const expected = getBearerToken();

  if (!header || !header.startsWith("Bearer ")) {
    res.status(401).json({
      jsonrpc: "2.0",
      error: { code: -32001, message: "Unauthorized: bearer token ontbreekt." },
      id: null,
    });
    return;
  }

  const provided = header.slice("Bearer ".length).trim();
  if (!safeEqual(provided, expected)) {
    res.status(401).json({
      jsonrpc: "2.0",
      error: { code: -32001, message: "Unauthorized: ongeldig bearer token." },
      id: null,
    });
    return;
  }

  next();
}
