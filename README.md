# Twilio Webhook Tester

PHP wrapper for Twilio CLI webhook plugin - Test Twilio SMS webhooks locally using the Twilio CLI webhook plugin.

## Features

- 🚀 Test SMS webhooks locally
- 🔒 Support for secured webhooks (with signature validation)
- 🔓 Support for unsecured webhooks
- 📝 Custom data parameters
- 🌐 GET and POST HTTP methods
- ⚡ Fast and lightweight
- 🔌 Uses official Twilio CLI webhook plugin
- 🎲 Automatic generation of realistic test data using Faker

## Prerequisites

This wrapper requires the Twilio CLI and webhook plugin to be installed:

1. **Install Twilio CLI:**
   ```bash
   # macOS/Linux
   brew tap twilio/brew && brew install twilio
   
   # Or using npm
   npm install -g twilio-cli
   
   # See: https://www.twilio.com/docs/twilio-cli/quickstart
   ```

2. **Install Webhook Plugin:**
   ```bash
   twilio plugins:install @twilio-labs/plugin-webhook
   ```

3. **Verify Installation:**
   ```bash
   twilio webhook:invoke --help
   ```

## Installation

```bash
composer require habeebdev/twilio-webhook-tester
```

## Usage

### Basic Usage

```bash
# Test an SMS webhook
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook

# Or using environment variable
# Set in .env: WEBHOOK_URL=https://your-webhook-url.com/webhook
vendor/bin/twilio-webhook
```

### With Custom Data

```bash
# Add custom parameters
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook \
  -d Body="Hello, how are you?" \
  -d From=+15551234567 \
  -d To=+15559876543
```

### HTTP Methods

```bash
# Use GET method
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook --method GET

# Use POST method (default)
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook --method POST
```

### Secured Webhooks

For webhooks that validate the `X-Twilio-Signature` header:

**Option 1: Using .env file (recommended)**

Create a `.env` file in your project root:

```bash
# .env file
TWILIO_ACCOUNT_SID=ACxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
```

Or with API Key/Secret:

```bash
# .env file
TWILIO_ACCOUNT_SID=ACxxxxx
TWILIO_API_KEY=SKxxxxx
TWILIO_API_SECRET=your_api_secret
```

Then run:
```bash
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook
```

**Option 2: Using command-line arguments**

```bash
# Using Auth Token
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook \
  --account-sid ACxxxxx \
  --auth-token your_auth_token_here

# Using API Key/Secret
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook \
  --account-sid ACxxxxx \
  --api-key SKxxxxx \
  --api-secret your_api_secret
```

### Unsecured Webhooks

For webhooks that don't validate signatures:

```bash
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook --no-signature
```

## Command Options

```
Usage:
  twilio-webhook [WEBHOOK_URL] [OPTIONS]

Arguments:
  WEBHOOK_URL              The webhook URL to invoke (optional if WEBHOOK_URL is set in .env)

Options:
  -a, --auth-token TOKEN   Twilio Auth Token (or set TWILIO_AUTH_TOKEN env var)
  --account-sid SID        Twilio Account SID (or set TWILIO_ACCOUNT_SID env var)
  --api-key KEY            Twilio API Key (or set TWILIO_API_KEY env var)
  --api-secret SECRET      Twilio API Secret (or set TWILIO_API_SECRET env var)
  -X, --method METHOD      HTTP method (GET or POST, default: POST)
  -d, --data-urlencode     Add data parameter (format: KEY=VALUE, can be used multiple times)
  --no-signature           Skip signature generation (for unsecured webhooks)
```

## Automatic Data Generation

The tool automatically generates realistic test data using [Faker](https://fakerphp.github.io/) when parameters are not provided:

### Automatically Generated Fields

- **MessageSid**: Unique SMS message SID (format: `SM...`)
- **SmsMessageSid**: Same as MessageSid
- **SmsSid**: Same as MessageSid
- **AccountSid**: Random account SID (format: `AC...`)
- **MessagingServiceSid**: From `TWILIO_MESSAGING_SERVICE_SID` env var (if set), otherwise not included
- **From**: Random US phone number (E.164 format) or from `FROM_PHONE_NUMBER` env var
- **To**: Random US phone number (E.164 format) or from `TO_PHONE_NUMBER` env var
- **Body**: Random English message (20-80 characters)
- **FromCountry/ToCountry**: "US"
- **FromState/ToState**: Random US state abbreviation
- **FromCity/ToCity**: Random US city name
- **FromZip/ToZip**: Random US zip code
- **NumMedia**: "0"
- **SmsStatus**: Random from ['received', 'sent', 'delivered']

### Overriding Default Values

You can override any automatically generated value using the `-d` option:

```bash
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook \
  -d Body="Custom message" \
  -d From=+15551234567 \
  -d MessageSid=SM1234567890abcdef
```

## Examples

### Example 1: Basic SMS Webhook

```bash
vendor/bin/twilio-webhook https://hello-messaging-1111-xxxxxx.twil.io/hello-messaging
```

Expected output (TwiML):
```xml
<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Message>Hello World</Message>
</Response>
```

### Example 2: Custom SMS Parameters

```bash
vendor/bin/twilio-webhook https://your-webhook-url.com/webhook \
  -d Body="Hi, how are you doing?" \
  -d From=+15551234567 \
  -d To=+15559876543
```

### Example 3: Testing Protected Webhook

```bash
# Using .env file (recommended)
# Add to .env: TWILIO_ACCOUNT_SID=ACxxxxx and TWILIO_AUTH_TOKEN=your_token
vendor/bin/twilio-webhook https://protected-webhook.twil.io/function

# Or using command-line arguments
vendor/bin/twilio-webhook https://protected-webhook.twil.io/function \
  --account-sid ACxxxxx \
  --auth-token your_auth_token_here
```

## Environment Variables

**Important**: The Twilio CLI requires authentication. You must set one of the following combinations in your `.env` file:

### Using .env File (Recommended)

Create a `.env` file in your project root:

**Option 1: API Key/Secret (Recommended)**
```bash
# .env file
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_API_KEY=SKxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_API_SECRET=your_api_secret_here
```

**Option 2: Auth Token**
```bash
# .env file
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
```

**Webhook URL (Optional)**
```bash
# .env file
WEBHOOK_URL=https://your-webhook-url.com/webhook  # Default webhook URL
```

**Phone Number Defaults (Optional)**
```bash
# .env file
FROM_PHONE_NUMBER=+15551234567  # Default From number
TO_PHONE_NUMBER=+15559876543    # Default To number
```

**Messaging Service SID (Optional)**
```bash
# .env file
TWILIO_MESSAGING_SERVICE_SID=MGxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx  # Messaging Service SID
```

**Quick Setup:**
```bash
# Copy the example file
cp .env.example .env

# Edit .env with your actual credentials
# The tool automatically loads .env - no need to source it!
vendor/bin/twilio-webhook https://example.com/webhook
```

**Note**: 
- `TWILIO_ACCOUNT_SID` is required for both authentication options
- For webhook signature validation, you need either `TWILIO_AUTH_TOKEN` OR (`TWILIO_API_KEY` + `TWILIO_API_SECRET`)
- `WEBHOOK_URL` can be set in `.env` file to avoid passing URL as argument each time
- Command-line arguments take precedence over `.env` file values
- If `FROM_PHONE_NUMBER` or `TO_PHONE_NUMBER` are not set, random phone numbers will be generated
- `TWILIO_MESSAGING_SERVICE_SID` is optional - if not set, it will not be included in the webhook data
- The `.env` file is gitignored for security. Never commit your actual credentials to version control.

## Requirements

- PHP ^8.0
- Twilio CLI installed and in PATH
- Twilio CLI webhook plugin installed (`@twilio-labs/plugin-webhook`)
- Node.js (required by Twilio CLI)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Related Resources

- [Twilio Webhook Plugin Documentation](https://www.twilio.com/en-us/blog/validate-webhooks-with-new-webhook-plugin-for-twilio-cli)
- [Twilio Webhook Security](https://www.twilio.com/docs/usage/webhooks/webhooks-security)
- [Twilio CLI](https://www.twilio.com/docs/twilio-cli/quickstart)

## Support

For issues and questions:
- Open an issue on GitHub
- Check the [Twilio Documentation](https://www.twilio.com/docs)

## Acknowledgments

This project is inspired by the [Twilio CLI Webhook Plugin](https://github.com/twilio-labs/plugin-webhook).
