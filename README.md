# NeuronSDK - PDF Knowledge Base with RAG

A PHP-based Retrieval Augmented Generation (RAG) system for chatting with PDF documents using AI agents.

## 🚀 Features

- PDF text extraction with `pdftotext`
- Vector storage for document embeddings
- AI chat interface with multiple providers
- Support for AWS Bedrock, OpenAI, Anthropic
- Persistent document storage

## 📋 Prerequisites

- PHP 8.0+ with Composer
- poppler-utils (`brew install poppler` on macOS)
- AWS account for Bedrock access

## 🛠️ Installation

1. Clone and install dependencies:
```bash
git clone <repo-url>
cd NeuronSDK
composer install
```

2. Copy environment file:
```bash
cp env.example .env
# Edit .env with your credentials
```

3. Add PDFs to `kb/` directory

## 🚀 Usage

**Setup documents (once):**
```bash
php setup_documents.php
```

**Chat with documents:**
```bash
php chat.php
```

## 🔧 Configuration

Create `.env` file with your AWS credentials:
```env
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_REGION=us-east-1
AWS_AGENT_ID=your_agent_id
AWS_AGENT_ALIAS_ID=your_alias_id
```

## 🏗️ Structure

- `kb/` - PDF documents
- `Providers/` - AI provider implementations
- `MyChatBot.php` - Main chatbot class
- `setup_documents.php` - Document loader
- `chat.php` - Interactive chat
