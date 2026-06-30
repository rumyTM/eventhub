"use client";
import { useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { vendorApi, ApiError } from "@/lib/api";
import type { KycDocument } from "@/lib/api/vendor";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth-context";

type DocType = KycDocument["type"];

const DOC_LABELS: Record<DocType, string> = {
  trade_license: "Trade License",
  nid: "National ID (NID)",
  bank_statement: "Bank Statement",
};

const REQUIRED_DOCS: DocType[] = ["trade_license", "nid", "bank_statement"];

export default function VendorKycPage() {
  const router = useRouter();
  const { refreshUser } = useAuth();
  const [docs, setDocs] = useState<Record<DocType, string>>({
    trade_license: "",
    nid: "",
    bank_statement: "",
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const mutation = useMutation({
    mutationFn: () => {
      const documents: KycDocument[] = REQUIRED_DOCS
        .filter((t) => docs[t].trim())
        .map((t) => ({ type: t, storage_path: docs[t].trim() }));
      return vendorApi.submitKyc(documents);
    },
    onSuccess: async () => {
      toast.success("KYC submitted — an admin will review your application.");
      await refreshUser();
      router.replace("/vendor");
    },
    onError: (err) => {
      if (err instanceof ApiError && err.errors) {
        const flat: Record<string, string> = {};
        for (const [field, msgs] of Object.entries(err.errors)) {
          flat[field] = msgs[0];
        }
        setErrors(flat);
      } else {
        toast.error(err instanceof ApiError ? err.message : "Failed to submit KYC.");
      }
    },
  });

  const hasAtLeastOne = REQUIRED_DOCS.some((t) => docs[t].trim());

  return (
    <div className="max-w-xl space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Submit KYC Documents</h1>
        <Link href="/vendor" className="text-sm text-primary underline">← Back</Link>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Required documents</CardTitle>
          <CardDescription>
            This is a demo environment — enter any reference path (e.g.{" "}
            <code className="text-xs">kyc/trade_license.pdf</code>). In production
            these would be signed upload URLs from secure storage.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          {REQUIRED_DOCS.map((type) => (
            <div key={type} className="space-y-2">
              <Label htmlFor={type}>{DOC_LABELS[type]}</Label>
              <Input
                id={type}
                value={docs[type]}
                onChange={(e) => setDocs((d) => ({ ...d, [type]: e.target.value }))}
                placeholder={`kyc/vendors/${type}.pdf`}
              />
              {errors[`documents.0.storage_path`] && type === "trade_license" && (
                <p className="text-xs text-destructive">{errors[`documents.0.storage_path`]}</p>
              )}
            </div>
          ))}

          {errors.documents && (
            <p className="text-xs text-destructive">{errors.documents}</p>
          )}

          <Button
            className="w-full"
            disabled={!hasAtLeastOne || mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {mutation.isPending ? "Submitting…" : "Submit for Review"}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
