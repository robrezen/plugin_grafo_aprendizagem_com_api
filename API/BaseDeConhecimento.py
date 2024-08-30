import uvicorn
import argparse
#####################################################################################################


async def app(scope, receive, send):
    ...


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description='Run the Learning Graph API')
    parser.add_argument('--host', type=str, default='localhost', help='Host to run the API')
    parser.add_argument('--port', type=int, default=8000, help='Port to run the API')
    parser.add_argument('--workers', type=int, default=256, help='Number of workers to run the API')
    parser.add_argument('--log-level', type=str, default='info', help='Log level')
    parser.add_argument('--env', type=str, default='dev', help='Environment')
    return parser


if __name__ == "__main__":
    parser = build_parser()
    args = parser.parse_args()

    kwargs = {'reload': True} if args.env == 'dev' else {'workers': args.workes, 'reload': False, 'log_level': 'info', 'debug': False}
    uvicorn.run('main:app', host=args.host, port=args.port, **kwargs)