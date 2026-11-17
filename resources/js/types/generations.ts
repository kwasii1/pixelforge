export type Money = {
    exact: string;
    display: string;
};

export type Option = {
    value: string;
    label: string;
};

/** Pixelforge's pricing tables with rates in nanodollars per million tokens. */
export type Pricing = {
    ratesPerMillion: {
        inputText: number;
        inputImage: number;
        output: number;
        reasoning: number;
    };
    charactersPerTextToken: number;
    textTokenVarianceBasisPoints: number;
    tokensPerReferenceImage: number;
    outputTokens: Record<string, Record<string, number>>;
    reasoningTokens: Record<string, { min: number; max: number }>;
    markup: { numerator: number; denominator: number };
};

export type TokenUsage = {
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
    total_tokens?: number;
};

export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

export type Generation = {
    id: string;
    prompt: string;
    size: string;
    sizeLabel: string;
    quality: string;
    qualityLabel: string;
    referenceImages: string[];
    status: GenerationStatus;
    statusLabel: string;
    isFinal: boolean;
    estimate: { min: Money; max: Money };
    price: Money | null;
    providerCost: Money | null;
    usage: TokenUsage | null;
    rates: Record<string, string> | null;
    imageUrl: string | null;
    imageUrlExpiresAt: string | null;
    imageExpired: boolean;
    failureReason: string | null;
    errorCode: string | null;
    createdAt: string;
    completedAt: string | null;
};
