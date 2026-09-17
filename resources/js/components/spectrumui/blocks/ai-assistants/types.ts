export type MessageRole = 'user' | 'assistant' | 'system';

export type MessageState = 'streaming' | 'complete' | 'error';

export interface Attachment {
  id: string;
  name: string;
  size: number;
  type: string;
  url?: string;
  previewUrl?: string;
}

export interface Citation {
  id: string;
  index: number;
  url: string;
  title: string;
  snippet?: string;
  favicon?: string;
}

export type ToolCallStatus = 'pending' | 'running' | 'success' | 'error' | 'cancelled';

export interface ToolCall {
  id: string;
  name: string;
  args?: Record<string, unknown>;
  result?: string;
  status: ToolCallStatus;
  startedAt?: number;
  completedAt?: number;
  children?: ToolCall[];
  parallel?: boolean;
}

export interface ReasoningStep {
  id: string;
  content: string;
}

export interface Reasoning {
  steps: ReasoningStep[];
  status: 'thinking' | 'complete';
  durationMs?: number;
}

export interface Message {
  id: string;
  role: MessageRole;
  content: string;
  createdAt?: number;
  state?: MessageState;
  attachments?: Attachment[];
  citations?: Citation[];
  toolCalls?: ToolCall[];
  reasoning?: Reasoning;
}

export interface Conversation {
  id: string;
  title: string;
  createdAt: number;
  updatedAt: number;
  modelId?: string;
  systemPrompt?: string;
  pinned?: boolean;
  messageCount?: number;
}

export interface SuggestedPrompt {
  id: string;
  label: string;
  prompt?: string;
  icon?: React.ReactNode;
}

export interface ModelOption {
  id: string;
  name: string;
  description?: string;
  badge?: string;
  icon?: React.ReactNode;
  disabled?: boolean;
  provider?: string;
}

export interface ErrorState {
  code?: string;
  message: string;
  retryable?: boolean;
  details?: Record<string, unknown>;
}
