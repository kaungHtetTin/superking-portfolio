"""Compatibility entry point for the isolated specification regression suite."""
from pathlib import Path
import runpy

if __name__ == '__main__':
    runpy.run_path(str(Path(__file__).with_name('specification.py')), run_name='__main__')
