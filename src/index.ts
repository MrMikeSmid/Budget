import "dotenv/config";
import { randomUUID } from "node:crypto";
import express from "express";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { isInitializeRequest } from "@modelcontextprotocol/sdk/types.js";
import type { Request, Response } from "express";
import { bearerAuth } from "./auth.js";
import { registerListEmailsTool } from "./tools/listEmails.js";
import { registerReadEmailTool } from "./tools/readEmail.js";
import { registerSearchEmailsTool } from "./tools/searchEmails.js";
import { registerSendEmailTool } from "./tools/sendEmail.js";

function createMcpServer(): McpServer {
  const server = new McpServer({
    name: "mcp-email-connector",
    version: "1.0.0",
  });

  registerListEmailsTool(server);
  registerReadEmailTool(server);
  registerSearchEmailsTool(server);
  registerSendEmailTool(server);

  return server;
}

const app = express();
app.use(express.json());

const MCP_PATH = "/mcp";
const transports: Record<string, StreamableHTTPServerTransport> = {};

app.get("/health", (_req, res) => {
  res.status(200).json({ status: "ok" });
});

app.post(MCP_PATH, bearerAuth, async (req: Request, res: Response) => {
  const sessionId = req.header("mcp-session-id");

  try {
    let transport: StreamableHTTPServerTransport;

    if (sessionId && transports[sessionId]) {
      transport = transports[sessionId];
    } else if (!sessionId && isInitializeRequest(req.body)) {
      transport = new StreamableHTTPServerTransport({
        sessionIdGenerator: () => randomUUID(),
        onsessioninitialized: (newSessionId) => {
          transports[newSessionId] = transport;
        },
      });

      transport.onclose = () => {
        if (transport.sessionId) {
          delete transports[transport.sessionId];
        }
      };

      const server = createMcpServer();
      await server.connect(transport);
    } else {
      res.status(400).json({
        jsonrpc: "2.0",
        error: { code: -32000, message: "Bad Request: geen geldige mcp-session-id opgegeven." },
        id: null,
      });
      return;
    }

    await transport.handleRequest(req, res, req.body);
  } catch (err) {
    // eslint-disable-next-line no-console
    console.error("Fout bij verwerken van MCP-verzoek:", err instanceof Error ? err.message : err);
    if (!res.headersSent) {
      res.status(500).json({
        jsonrpc: "2.0",
        error: { code: -32603, message: "Interne serverfout." },
        id: null,
      });
    }
  }
});

async function handleSessionRequest(req: Request, res: Response): Promise<void> {
  const sessionId = req.header("mcp-session-id");
  if (!sessionId || !transports[sessionId]) {
    res.status(400).send("Ongeldige of ontbrekende mcp-session-id.");
    return;
  }
  const transport = transports[sessionId];
  await transport.handleRequest(req, res);
}

app.get(MCP_PATH, bearerAuth, handleSessionRequest);
app.delete(MCP_PATH, bearerAuth, handleSessionRequest);

const port = Number(process.env.PORT ?? 3000);

app.listen(port, () => {
  // eslint-disable-next-line no-console
  console.log(`MCP email connector luistert op poort ${port} (endpoint: ${MCP_PATH})`);
});
