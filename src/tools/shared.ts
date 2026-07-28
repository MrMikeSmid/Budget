import type { FetchMessageObject } from "imapflow";

export interface EmailSummary {
  id: number;
  from: string;
  to: string;
  subject: string;
  date: string | null;
  unread: boolean;
  size: number | null;
}

interface AddressLike {
  name?: string;
  address?: string;
}

export function formatAddressList(addresses?: AddressLike[]): string {
  if (!addresses || addresses.length === 0) {
    return "";
  }
  return addresses
    .map((a) => (a.name ? `${a.name} <${a.address}>` : (a.address ?? "")))
    .join(", ");
}

export function toSummary(msg: FetchMessageObject): EmailSummary {
  return {
    id: msg.uid,
    from: formatAddressList(msg.envelope?.from),
    to: formatAddressList(msg.envelope?.to),
    subject: msg.envelope?.subject ?? "(geen onderwerp)",
    date: msg.envelope?.date ? new Date(msg.envelope.date).toISOString() : null,
    unread: msg.flags ? !msg.flags.has("\\Seen") : true,
    size: msg.size ?? null,
  };
}

export function jsonResult(payload: unknown) {
  return {
    content: [
      {
        type: "text" as const,
        text: JSON.stringify(payload, null, 2),
      },
    ],
  };
}

export function errorResult(message: string) {
  return {
    content: [
      {
        type: "text" as const,
        text: message,
      },
    ],
    isError: true,
  };
}
