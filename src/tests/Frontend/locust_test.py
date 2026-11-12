import importlib.util
import unittest
from pathlib import Path

spec = importlib.util.spec_from_file_location('athena_locust', Path(__file__).parents[2] / 'scripts/jmeter/athena-locust.py')
kit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(kit)


class ResponseTests(unittest.TestCase):
    def test_rejects_login_wrong_actor_and_empty_documents(self):
        user = {'workspace': 'faculty', 'actorId': 12}
        html = '<body data-active-workspace="faculty" data-auth-user-id="12"></body>'
        self.assertIsNone(kit.response_error(200, 'text/html', html.encode(), {}, user))
        self.assertIsNotNone(kit.response_error(302, 'text/html', b'', {}, user))
        self.assertIsNotNone(kit.response_error(200, 'text/html', html.replace('12', '13').encode(), {}, user))
        self.assertIsNotNone(kit.response_error(200, 'text/html', b'<form action="/login">', {}, user))
        self.assertIsNotNone(kit.response_error(200, 'application/pdf', b'', {'binary': True}, user))
        self.assertIsNotNone(kit.response_error(200, 'text/html', html.encode(), {'binary': True}, user))
        self.assertIsNone(kit.response_error(200, 'application/pdf', b'%PDF-document', {'binary': True}, user))


if __name__ == '__main__':
    unittest.main()
